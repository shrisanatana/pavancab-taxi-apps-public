package com.pavancab.niranjan

import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.border
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.lifecycleScope
import androidx.compose.ui.platform.LocalLifecycleOwner
import com.google.firebase.messaging.FirebaseMessaging
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UpdateInfo
import com.pavancab.niranjan.data.UpdateManager
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.network.ApiClient
import com.pavancab.niranjan.ui.UpdateDialog
import com.pavancab.niranjan.ui.auth.AuthScreen
import com.pavancab.niranjan.ui.booking.BookingConfirmScreen
import com.pavancab.niranjan.ui.home.HomeScreen
import com.pavancab.niranjan.ui.notifications.NotificationsScreen
import com.pavancab.niranjan.ui.profile.ProfileScreen
import com.pavancab.niranjan.ui.rides.MyRidesScreen
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {

    private val notifPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { _ -> }

    private val pendingBookingId = mutableStateOf("")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        CrashLogger.log("ACTIVITY", "onCreate", "MainActivity")
        pendingBookingId.value = intent?.getStringExtra("booking_id") ?: ""
        requestNotifPermission()
        fetchAndSaveFcmToken()
        try {
            setContent {
                PavanCabTheme {
                    PavanCabApp(notifBookingId = pendingBookingId.value)
                }
            }
        } catch (e: Exception) {
            CrashLogger.log("CRASH", "setContent failed: ${e.message}", "MainActivity", e)
            try {
                setContent {
                    MaterialTheme {
                        Surface(modifier = Modifier.fillMaxSize(), color = Color(0xFF070A12)) {
                            Column(modifier = Modifier.padding(32.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
                                Text("PAVANCAB", color = Color(0xFFF59E0B), fontSize = 24.sp, fontWeight = FontWeight.Black)
                                Spacer(Modifier.height(16.dp))
                                Text("Loading error. Please restart.", color = Color.Gray, fontSize = 14.sp)
                            }
                        }
                    }
                }
            } catch (_: Exception) {}
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        val id = intent.getStringExtra("booking_id")
        if (!id.isNullOrBlank()) pendingBookingId.value = id
    }

    private fun requestNotifPermission() {
        if (Build.VERSION.SDK_INT >= 33) {
            if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                notifPermissionLauncher.launch(android.Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

    private fun fetchAndSaveFcmToken() {
        try {
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val token = task.result
                    if (!token.isNullOrEmpty()) {
                        launch {
                            try {
                                UserPrefs.saveFcmToken(this@MainActivity, token)
                                val repo = Repository(this@MainActivity)
                                repo.saveFcmTokenToServer(token)
                                CrashLogger.log("FCM", "Token saved: ${token.take(20)}...", "MainActivity")
                            } catch (e: Exception) {
                                CrashLogger.log("FCM", "Token save failed: ${e.message}", "MainActivity", e)
                            }
                        }
                    }
                }
            }
        } catch (e: Exception) {
            CrashLogger.log("FCM", "FirebaseMessaging.token failed: ${e.message}", "MainActivity", e)
        }
    }

    private fun launch(block: suspend () -> Unit) {
        lifecycleScope.launch(kotlinx.coroutines.Dispatchers.IO) { block() }
    }
}

enum class BottomTab(val label: String, val icon: ImageVector) {
    Book("Book", Icons.Default.LocalTaxi),
    Rides("My Rides", Icons.Default.List),
    Profile("Profile", Icons.Default.Person)
}

sealed class Screen {
    data object Auth : Screen()
    data class Main(val tab: BottomTab = BottomTab.Book, val highlightBookingId: Int? = null) : Screen()
    data class BookingConfirm(
        val tripType: String, val pickup: String, val drop: String,
        val duration: String, val cabType: String, val fare: Double
    ) : Screen()
    data object Notifications : Screen()
}

@Composable
fun PavanCabApp(notifBookingId: String = "") {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var checkingSession by remember { mutableStateOf(true) }
    var currentScreen by remember { mutableStateOf<Screen>(Screen.Auth) }
    var currentTab by remember { mutableStateOf(BottomTab.Book) }
    var sessionReady by remember { mutableStateOf(false) }
    var ridesRefresh by remember { mutableIntStateOf(0) }
    var updateInfo by remember { mutableStateOf<UpdateInfo?>(null) }

    // FCM push wakes the current screen so ride status updates live (no tab switch / reopen needed)
    val appContext = context.applicationContext
    DisposableEffect(Unit) {
        val receiver = object : android.content.BroadcastReceiver() {
            override fun onReceive(c: android.content.Context?, i: android.content.Intent?) {
                ridesRefresh++
            }
        }
        ContextCompat.registerReceiver(appContext, receiver, android.content.IntentFilter("com.pavancab.niranjan.REFRESH_RIDES"), ContextCompat.RECEIVER_NOT_EXPORTED)
        onDispose {
            runCatching { appContext.unregisterReceiver(receiver) }
        }    }

    var bTripType by remember { mutableStateOf("") }
    var bPickup by remember { mutableStateOf("") }
    var bDrop by remember { mutableStateOf("") }
    var bDuration by remember { mutableStateOf("") }
    var bCabType by remember { mutableStateOf("") }
    var bFare by remember { mutableDoubleStateOf(0.0) }

    LaunchedEffect(Unit) {
        try {
            val loggedIn = UserPrefs.isLoggedIn(context)
            val phone = UserPrefs.getPhone(context)
            if (loggedIn && phone.isNotEmpty()) {
                val savedSessionId = UserPrefs.getSessionId(context)
                if (savedSessionId.isNotEmpty()) {
                    ApiClient.cookieJar.restoreSession(savedSessionId)
                }
                val repo = Repository(context)
                var check = repo.checkSession()
                var success = try { check.get("success").asBoolean } catch (_: Exception) { false }

                // PHP session died server-side? Silent auto-login via persistent remember-token
                if (!success && ApiClient.sessionExpired) ApiClient.clearSessionExpiredFlag()
                if (!success) {
                    val auto = repo.autoLogin(context)
                    success = try { auto.get("success").asBoolean } catch (_: Exception) { false }
                    if (success) {
                        val u = try { auto.getAsJsonObject("user") } catch (_: Exception) { null }
                        val name = try { u?.get("name")?.asString ?: "" } catch (_: Exception) { "" }
                        val mobile = try { u?.get("mobile")?.asString ?: phone } catch (_: Exception) { phone }
                        val email = try { u?.get("email")?.asString ?: "" } catch (_: Exception) { "" }
                        val role = try { u?.get("role")?.asString ?: "user" } catch (_: Exception) { "user" }
                        val uid = try { u?.get("id")?.asInt ?: 0 } catch (_: Exception) { 0 }
                        UserPrefs.saveUser(context, uid, name, mobile, email, role, role == "admin", role == "admin" || role == "team")
                        val sessId = ApiClient.cookieJar.getSessionId()
                        if (sessId.isNotEmpty()) UserPrefs.saveSessionId(context, sessId)
                    }
                }

                if (success) {
                    currentScreen = Screen.Main(BottomTab.Rides)
                    try {
                        val fcmTok = UserPrefs.getFcmToken(context)
                        if (fcmTok.isNotEmpty()) {
                            kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.IO).launch {
                                Repository(context).saveFcmTokenToServer(fcmTok)
                            }
                        }
                    } catch (_: Exception) {}
                } else {
                    // Only force login if we truly can't authenticate (and we have network feedback)
                    val hadNetwork = Repository.lastError == null || Repository.lastError != "OFFLINE"
                    if (hadNetwork) {
                        UserPrefs.clearUser(context)
                        UserPrefs.clearAutoToken(context)
                        ApiClient.cookieJar.clear()
                    } else {
                        // Offline: keep cached identity so FCM token stays mapped & user sees data
                        currentScreen = Screen.Main(BottomTab.Rides)
                    }
                }
            }
        } catch (e: Exception) {
            CrashLogger.log("ERROR", "Session check: ${e.message}", "NavHost", e)
        }
        checkingSession = false
        sessionReady = true
    }

    // Check for an app update once on launch
    LaunchedEffect(Unit) {
        try {
            updateInfo = UpdateManager.check(context)
        } catch (e: Exception) {
            CrashLogger.log("UPDATE", "check failed: ${e.message}", "NavHost", e)
        }
    }

    // Notification deep-link: jump to the ride mentioned in the push
    LaunchedEffect(notifBookingId, sessionReady) {
        if (sessionReady && notifBookingId.isNotBlank()) {
            val id = notifBookingId.toIntOrNull()
            if (id != null && id > 0 && currentScreen !is Screen.Auth) {
                currentTab = BottomTab.Rides
                currentScreen = Screen.Main(BottomTab.Rides, id)
            }
        }
    }

    // Global session-expiry watcher — tries silent auto-login first, only logs out as last resort
    LaunchedEffect(Unit) {
        while (true) {
            delay(2000)
            if (ApiClient.sessionExpired) {
                ApiClient.clearSessionExpiredFlag()
                val repo = Repository(context)
                val auto = repo.autoLogin(context)
                val ok = try { auto.get("success").asBoolean } catch (_: Exception) { false }
                if (ok) {
                    // Session restored silently — refresh local user copy
                    val u = try { auto.getAsJsonObject("user") } catch (_: Exception) { null }
                    val name = try { u?.get("name")?.asString ?: "" } catch (_: Exception) { "" }
                    val mobile = try { u?.get("mobile")?.asString ?: UserPrefs.getPhone(context) } catch (_: Exception) { UserPrefs.getPhone(context) }
                    val email = try { u?.get("email")?.asString ?: "" } catch (_: Exception) { "" }
                    val role = try { u?.get("role")?.asString ?: "user" } catch (_: Exception) { "user" }
                    val uid = try { u?.get("id")?.asInt ?: 0 } catch (_: Exception) { 0 }
                    UserPrefs.saveUser(context, uid, name, mobile, email, role, role == "admin", role == "admin" || role == "team")
                    val sessId = ApiClient.cookieJar.getSessionId()
                    if (sessId.isNotEmpty()) UserPrefs.saveSessionId(context, sessId)
                } else if (Repository.lastError != "OFFLINE") {
                    UserPrefs.clearUser(context)
                    UserPrefs.clearAutoToken(context)
                    ApiClient.cookieJar.clear()
                    currentScreen = Screen.Auth
                    break
                }
            }
        }
    }

    Box(modifier = Modifier.fillMaxSize()) {
    if (checkingSession) {
        // ══════ GOA PARADISE SPLASH ══════
        val pulse = rememberInfiniteTransition(label = "splash")
        val scale by pulse.animateFloat(0.9f, 1.1f, infiniteRepeatable(tween(850, easing = androidx.compose.animation.core.FastOutSlowInEasing), RepeatMode.Reverse), label = "s")
        val ringAlpha by pulse.animateFloat(0.15f, 0.5f, infiniteRepeatable(tween(1200), RepeatMode.Reverse), label = "r")
        val sway by pulse.animateFloat(-6f, 6f, infiniteRepeatable(tween(1600, easing = androidx.compose.animation.core.FastOutSlowInEasing), RepeatMode.Reverse), label = "sway")
        val shine by pulse.animateFloat(0.4f, 1f, infiniteRepeatable(tween(1100), RepeatMode.Reverse), label = "sh")
        Box(
            modifier = Modifier.fillMaxSize().background(
                Brush.verticalGradient(listOf(Color(0xFF070C18), Color(0xFF0B1E33), DarkBg))
            )
        ) {
            // Palm corners
            Text("\uD83C\uDF34", fontSize = 58.sp, modifier = Modifier.align(Alignment.TopStart).padding(start = 14.dp, top = 20.dp).rotate(sway))
            Text("\uD83C\uDF34", fontSize = 46.sp, modifier = Modifier.align(Alignment.TopEnd).padding(end = 18.dp, top = 48.dp).rotate(-sway))
            Text("\uD83C\uDF34", fontSize = 38.sp, modifier = Modifier.align(Alignment.BottomStart).padding(start = 26.dp, bottom = 60.dp).rotate(sway * 0.7f))

            Column(modifier = Modifier.align(Alignment.Center), horizontalAlignment = Alignment.CenterHorizontally) {
                // Glow rings + taxi badge
                Box(contentAlignment = Alignment.Center) {
                    Box(modifier = Modifier.size(150.dp).clip(CircleShape).background(Gold.copy(alpha = 0.05f * shine)).border(1.dp, Gold.copy(alpha = ringAlpha), CircleShape))
                    Box(modifier = Modifier.size(122.dp).clip(CircleShape).background(Gold.copy(alpha = 0.08f)).border(1.dp, Gold.copy(alpha = ringAlpha + 0.15f), CircleShape))
                    Surface(modifier = Modifier.size(96.dp).scale(scale), shape = RoundedCornerShape(30.dp), color = Gold.copy(alpha = 0.16f), border = androidx.compose.foundation.BorderStroke(2.dp, Gold)) {
                        Box(contentAlignment = Alignment.Center) {
                            Icon(Icons.Default.LocalTaxi, null, tint = Gold, modifier = Modifier.size(50.dp))
                        }
                    }
                }
                Spacer(Modifier.height(22.dp))
                Text("PAVANCAB", color = Gold, fontSize = 30.sp, fontWeight = FontWeight.Black, letterSpacing = 7.sp)
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.width(28.dp).height(1.5.dp).background(Gold.copy(alpha = 0.7f)))
                    Spacer(Modifier.width(10.dp))
                    Text("GOA'S NO.1 CAB SERVICE", color = White, fontSize = 12.sp, fontWeight = FontWeight.Black, letterSpacing = 2.sp)
                    Spacer(Modifier.width(10.dp))
                    Box(modifier = Modifier.width(28.dp).height(1.5.dp).background(Gold.copy(alpha = 0.7f)))
                }
                Spacer(Modifier.height(14.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf("\uD83C\uDFD6\uFE0F Beaches", "\u2708\uFE0F Airport", "\uD83C\uDFC4 Sightseeing", "\u23F1 Hourly").forEach { chip ->
                        Surface(shape = RoundedCornerShape(20.dp), color = White.copy(alpha = 0.06f), border = androidx.compose.foundation.BorderStroke(1.dp, Gold.copy(alpha = 0.25f))) {
                            Text(chip, color = Gray300, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(horizontal = 11.dp, vertical = 6.dp))
                        }
                    }
                }
            }

            Column(modifier = Modifier.align(Alignment.BottomCenter).padding(bottom = 44.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Text("\uD83C\uDF34 Serving all of GOA \uD83C\uDF34", color = Gold.copy(alpha = 0.85f), fontSize = 12.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)
                Spacer(Modifier.height(4.dp))
                Text("Baga \u2022 Calangute \u2022 Anjuna \u2022 Panaji \u2022 Airport & more", color = Gray500, fontSize = 9.sp)
            }
        }
    } else {
        when (val screen = currentScreen) {
        is Screen.Auth -> {
            AuthScreen(onLoginSuccess = {
                currentScreen = Screen.Main(BottomTab.Rides)
            })
        }

        is Screen.Main -> {
            Column(modifier = Modifier.fillMaxSize().background(DarkBg)) {
                Box(modifier = Modifier.weight(1f)) {
                    when (currentTab) {
                        BottomTab.Book -> HomeScreen(
                            onNavigateToBooking = { tripType, pickup, drop, duration, cabType, fare ->
                                bTripType = tripType; bPickup = pickup; bDrop = drop
                                bDuration = duration; bCabType = cabType; bFare = fare
                                currentScreen = Screen.BookingConfirm(tripType, pickup, drop, duration, cabType, fare)
                            },
                            onNotifications = { currentScreen = Screen.Notifications },
                            onOpenRides = { currentTab = BottomTab.Rides }
                        )
                        BottomTab.Rides -> MyRidesScreen(
                            highlightBookingId = screen.highlightBookingId,
                            onBookNow = { currentTab = BottomTab.Book },
                            refreshTrigger = ridesRefresh
                        )
                        BottomTab.Profile -> ProfileScreen(
                            onLoggedOut = { currentScreen = Screen.Auth }
                        )
                    }
                }

                NavigationBar(containerColor = CardBg, contentColor = White, tonalElevation = 0.dp) {
                    BottomTab.entries.forEach { tab ->
                        NavigationBarItem(
                            icon = { Icon(tab.icon, contentDescription = tab.label) },
                            label = { Text(tab.label, fontSize = 11.sp, fontWeight = if (currentTab == tab) FontWeight.Bold else FontWeight.Normal) },
                            selected = currentTab == tab,
                            onClick = { currentTab = tab },
                            colors = NavigationBarItemDefaults.colors(
                                selectedIconColor = DarkBg,
                                selectedTextColor = Gold,
                                indicatorColor = Gold,
                                unselectedIconColor = Gray500,
                                unselectedTextColor = Gray500
                            )
                        )
                    }
                }
            }
        }

        is Screen.BookingConfirm -> {
            BookingConfirmScreen(
                tripType = screen.tripType, pickup = screen.pickup, drop = screen.drop,
                duration = screen.duration, cabType = screen.cabType, fare = screen.fare,
                onBookingDone = {
                    currentTab = BottomTab.Rides
                    currentScreen = Screen.Main(BottomTab.Rides)
                },
                onBack = { currentScreen = Screen.Main(BottomTab.Book) }
            )
        }

        is Screen.Notifications -> {
            NotificationsScreen(onBack = { currentScreen = Screen.Main(BottomTab.Book) })
        }
        }
    }

    updateInfo?.let { info ->
        UpdateDialog(
            info = info,
            onRemindLater = {
                UpdateManager.rememberDismissed(context, info.latestVersionCode)
                updateInfo = null
            },
            onDismiss = { updateInfo = null }
        )
    }
    }
}
