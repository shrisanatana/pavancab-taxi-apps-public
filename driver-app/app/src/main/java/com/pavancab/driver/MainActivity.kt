package com.pavancab.driver

import android.Manifest
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.animation.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import android.widget.Toast
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.pavancab.driver.data.Repository
import com.pavancab.driver.data.UserPrefs
import com.pavancab.driver.model.Booking
import com.pavancab.driver.network.ApiClient
import com.pavancab.driver.ui.auth.AuthScreen
import com.pavancab.driver.ui.auth.NotApprovedScreen
import com.pavancab.driver.ui.components.LoadingOverlay
import com.pavancab.driver.ui.earnings.EarningsScreen
import com.pavancab.driver.ui.home.HomeScreen
import com.pavancab.driver.ui.profile.ProfileScreen
import com.pavancab.driver.ui.ride.ActiveRideScreen
import com.pavancab.driver.ui.ride.MyRidesScreen
import com.pavancab.driver.ui.subscription.SubscriptionScreen
import com.pavancab.driver.ui.wallet.WalletScreen
import com.pavancab.driver.ui.theme.*
import com.google.firebase.messaging.FirebaseMessaging
import com.razorpay.Checkout
import com.razorpay.PaymentResultListener
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity(), PaymentResultListener {

    private var paymentCallback: PaymentResultListener? = null

    fun setPaymentCallback(listener: PaymentResultListener?) { paymentCallback = listener }

    override fun onPaymentSuccess(razorpayPaymentId: String?) {
        paymentCallback?.onPaymentSuccess(razorpayPaymentId)
    }

    override fun onPaymentError(code: Int, description: String?) {
        paymentCallback?.onPaymentError(code, description)
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        requestPermissions()
        Checkout.preload(applicationContext)
        setContent { DriverAppContent(this) }
    }

    private fun requestPermissions() {
        val perms = mutableListOf<String>()
        if (Build.VERSION.SDK_INT >= 33) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED)
                perms.add(Manifest.permission.POST_NOTIFICATIONS)
        }
        if (perms.isNotEmpty()) ActivityCompat.requestPermissions(this, perms.toTypedArray(), 101)
    }
}

@Composable
fun DriverAppContent(activity: MainActivity? = null) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var loggedIn by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(true) }
    var notApprovedPhone by remember { mutableStateOf<String?>(null) }
    var selectedTab by remember { mutableIntStateOf(0) }
    var selectedBooking by remember { mutableStateOf<Booking?>(null) }
    var showSubscription by remember { mutableStateOf(false) }
    var showWallet by remember { mutableStateOf(false) }
    var refreshTrigger by remember { mutableIntStateOf(0) }
    val repo = remember { Repository(context) }

    // Check session on startup
    LaunchedEffect(Unit) {
        try {
            val saved = UserPrefs.getSession(context)
            if (!saved.isNullOrBlank()) {
                ApiClient.cookieJar.restoreSession(saved)
            }
            loggedIn = UserPrefs.isLoggedIn(context)
            if (loggedIn) {
                val res = ApiClient.rawGet("api/driver.php?action=check-session")
                if (res.get("success")?.asBoolean != true) {
                    loggedIn = false
                    UserPrefs.clear(context)
                } else {
                    val approved = res.get("approved")?.asBoolean ?: true
                    if (!approved) notApprovedPhone = UserPrefs.getPhone(context)
                }
            }
        } catch (_: Exception) { loggedIn = false }
        loading = false

        // Save FCM token
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                if (!token.isNullOrBlank()) {
                    scope.launch {
                        UserPrefs.saveFcmToken(context, token)
                        if (loggedIn) {
                            repo.saveFcmTokenToServer(token)
                        }
                    }
                }
            }
        }
    }

    // Refresh broadcast
    val refreshReceiver = remember {
        object : BroadcastReceiver() {
            override fun onReceive(ctx: Context?, intent: Intent?) {
                refreshTrigger++
            }
        }
    }
    DisposableEffect(Unit) {
        val filter = IntentFilter("com.pavancab.driver.REFRESH_DATA")
        if (Build.VERSION.SDK_INT >= 33) {
            context.registerReceiver(refreshReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
        } else {
            context.registerReceiver(refreshReceiver, filter)
        }
        onDispose { try { context.unregisterReceiver(refreshReceiver) } catch (_: Exception) {} }
    }

    // Re-check approval on any data refresh (REVOKED/APPROVED FCM drives this)
    LaunchedEffect(refreshTrigger) {
        if (loggedIn && notApprovedPhone == null) {
            try {
                val res = ApiClient.rawGet("api/driver.php?action=check-approval")
                if (res.get("success")?.asBoolean == true && res.get("approved")?.asBoolean == false) {
                    notApprovedPhone = UserPrefs.getPhone(context)
                }
            } catch (_: Exception) {}
        }
    }

    // Global session-expiry watcher
    LaunchedEffect(Unit) {
        while (true) {
            delay(3000)
            if (loggedIn && ApiClient.sessionExpired) {
                ApiClient.clearSessionExpiredFlag()
                loggedIn = false
                UserPrefs.clear(context)
                ApiClient.cookieJar.clear()
                Toast.makeText(context, "Session expired. Please login again.", Toast.LENGTH_LONG).show()
                break
            }
        }
    }

    DriverTheme {
        when {
            loading -> Box(modifier = Modifier.fillMaxSize().background(DarkBg), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = Gold, modifier = Modifier.size(48.dp), strokeWidth = 3.dp)
                    Spacer(Modifier.height(16.dp))
                    Text("Loading...", color = Gray300, fontSize = 14.sp)
                }
            }
            notApprovedPhone != null -> NotApprovedScreen(
                phone = notApprovedPhone!!,
                revoked = true,
                onBack = {
                    notApprovedPhone = null
                    loggedIn = false
                    scope.launch {
                        UserPrefs.clear(context)
                    }
                    ApiClient.cookieJar.clear()
                }
            )
            !loggedIn -> AuthScreen(
                onLoginSuccess = { loggedIn = true },
                onNotApproved = { }
            )
            selectedBooking != null -> ActiveRideScreen(
                booking = selectedBooking!!,
                repo = repo,
                onBack = { selectedBooking = null },
                onStatusChanged = { selectedBooking = null },
                onSubscription = { selectedBooking = null; showSubscription = true }
            )
            showSubscription -> SubscriptionScreen(
                repo = repo,
                onBack = { showSubscription = false },
                activity = activity
            )
            showWallet -> WalletScreen(
                repo = repo,
                onBack = { showWallet = false; refreshTrigger++ },
                activity = activity
            )
            else -> MainScaffold(repo, selectedTab, { selectedTab = it }, { selectedBooking = it }, { loggedIn = false }, { showSubscription = true }, refreshTrigger, { showWallet = true })
        }
    }
}

@Composable
private fun MainScaffold(
    repo: Repository,
    selectedTab: Int,
    onTabSelected: (Int) -> Unit,
    onBookingClick: (Booking) -> Unit,
    onLogout: () -> Unit,
    onSubscription: () -> Unit,
    refreshTrigger: Int = 0,
    onWallet: () -> Unit = {}
) {
    Scaffold(
        containerColor = DarkBg,
        bottomBar = {
            Surface(color = DarkBgLighter, shape = RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp), border = BorderStroke(1.dp, CardBorder)) {
                Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 8.dp), horizontalArrangement = Arrangement.SpaceEvenly) {
                    listOf(
                        Triple("Home", Icons.Default.Home, 0),
                        Triple("Rides", Icons.Default.List, 1),
                        Triple("Earnings", Icons.Default.Wallet, 2),
                        Triple("Profile", Icons.Default.Person, 3)
                    ).forEach { (label, icon, idx) ->
                        val sel = selectedTab == idx
                        Column(
                            modifier = Modifier.clip(RoundedCornerShape(10.dp)).clickable { onTabSelected(idx) }.padding(horizontal = 12.dp, vertical = 4.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Icon(icon, null, tint = if (sel) Gold else Gray500, modifier = Modifier.size(22.dp))
                            Text(label, color = if (sel) Gold else Gray500, fontSize = 10.sp, fontWeight = if (sel) FontWeight.Black else FontWeight.Medium)
                        }
                    }
                }
            }
        }
    ) { padding ->
        Box(modifier = Modifier.padding(padding)) {
            when (selectedTab) {
                0 -> HomeScreen(repo = repo, onBookingClick = onBookingClick, onNavigate = {}, onSubscription = onSubscription, refreshTrigger = refreshTrigger, onWallet = onWallet)
                1 -> MyRidesScreen(repo = repo, onBookingClick = onBookingClick, refreshTrigger = refreshTrigger)
                2 -> EarningsScreen(repo = repo, refreshTrigger = refreshTrigger)
                3 -> ProfileScreen(repo = repo, onLogout = onLogout, onSubscription = onSubscription, onWallet = onWallet, refreshTrigger = refreshTrigger)
            }
        }
    }
}
