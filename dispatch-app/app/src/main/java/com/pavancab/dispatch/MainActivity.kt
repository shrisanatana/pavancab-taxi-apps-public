package com.pavancab.dispatch

import android.Manifest
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.*
import androidx.core.content.ContextCompat
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.compose.ui.platform.LocalLifecycleOwner
import com.google.android.gms.tasks.OnCompleteListener
import com.google.firebase.messaging.FirebaseMessaging
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.network.ApiClient
import com.pavancab.dispatch.ui.auth.AuthScreen
import com.pavancab.dispatch.ui.bookings.BookingDetailScreen
import com.pavancab.dispatch.ui.bookings.BookingsScreen
import com.pavancab.dispatch.ui.commission.CommissionScreen
import com.pavancab.dispatch.ui.dashboard.DashboardScreen
import com.pavancab.dispatch.ui.drivers.DriversScreen
import com.pavancab.dispatch.ui.phonebooking.PhoneBookingScreen
import com.pavancab.dispatch.ui.profile.ProfileScreen
import com.pavancab.dispatch.ui.team.TeamScreen
import com.pavancab.dispatch.ui.users.ActiveUsersScreen
import com.pavancab.dispatch.ui.users.UserDetailScreen
import com.pavancab.dispatch.ui.settings.WhatsAppConfigScreen
import com.pavancab.dispatch.ui.settings.DriverConfigScreen
import com.pavancab.dispatch.ui.reports.ReportsScreen
import com.pavancab.dispatch.ui.drivers.DriverDetailScreen
import com.pavancab.dispatch.ui.bookings.EditBookingScreen
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        requestNotificationPermission()
        retrieveAndPersistSession()
        setupFCM()

        setContent {
            DispatchTheme {
                DispatchNav()
            }
        }
    }

    private fun requestNotificationPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val launcher = registerForActivityResult(ActivityResultContracts.RequestPermission()) { _ -> }
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        }
    }

    private fun retrieveAndPersistSession() {
        val saved = runCatching {
            kotlinx.coroutines.runBlocking { UserPrefs.getSession(this@MainActivity) }
        }.getOrNull()
        if (!saved.isNullOrBlank()) {
            ApiClient.cookieJar.restoreSession(saved)
        }
    }

    private fun setupFCM() {
        FirebaseMessaging.getInstance().token.addOnCompleteListener(OnCompleteListener { task ->
            if (!task.isSuccessful) return@OnCompleteListener
            val token = task.result
            kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.IO).launch {
                UserPrefs.saveFcmToken(this@MainActivity, token)
                try {
                    ApiClient.rawPost("api/dispatch.php?action=save_fcm_token", mapOf("fcm_token" to token))
                } catch (_: Exception) {}
            }
        })
    }
}

sealed class Screen {
    object Dashboard : Screen()
    object Bookings : Screen()
    object Drivers : Screen()
    object Team : Screen()
    object Profile : Screen()
    object PhoneBooking : Screen()
    object ActiveUsers : Screen()
    object WhatsAppConfig : Screen()
    object Commission : Screen()
    object Reports : Screen()
    class BookingDetail(val id: Int) : Screen()
    class EditBooking(val id: Int) : Screen()
    class DriverDetail(val id: Int) : Screen()
    class UserDetail(val phone: String, val email: String, val userId: Int) : Screen()
    object DriverConfig : Screen()
}

@Composable
fun DispatchNav() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var isLoggedIn by remember { mutableStateOf(false) }
    var checkingAuth by remember { mutableStateOf(true) }
    var currentScreen by remember { mutableStateOf<Screen>(Screen.Dashboard) }

    LaunchedEffect(Unit) {
        isLoggedIn = UserPrefs.isLoggedIn(context)
        val session = UserPrefs.getSession(context)
        if (session.isNotBlank()) {
            ApiClient.cookieJar.restoreSession(session)
        }
        checkingAuth = false
    }

    val lifecycleOwner = LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME && isLoggedIn) {
                scope.launch {
                    try {
                        val repo = Repository(context)
                        val result = repo.checkTeamAccess()
                        val valid = result.safeBool("valid") == true
                        if (valid == false && result.has("error")) {
                            isLoggedIn = false
                            UserPrefs.clear(context)
                            ApiClient.cookieJar.clear()
                            Toast.makeText(context, "Session expired. Please login again.", Toast.LENGTH_LONG).show()
                        }
                    } catch (_: Exception) {}
                }
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose { lifecycleOwner.lifecycle.removeObserver(observer) }
    }

    // Global session-expiry watcher
    LaunchedEffect(Unit) {
        while (true) {
            delay(3000)
            if (isLoggedIn && ApiClient.sessionExpired) {
                ApiClient.clearSessionExpiredFlag()
                isLoggedIn = false
                UserPrefs.clear(context)
                ApiClient.cookieJar.clear()
                Toast.makeText(context, "Session expired. Please login again.", Toast.LENGTH_LONG).show()
                break
            }
        }
    }

    if (checkingAuth) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = Gold)
        }
        return
    }

    if (!isLoggedIn) {
        AuthScreen(onLoginSuccess = { isLoggedIn = true })
        return
    }

    Column(modifier = Modifier.fillMaxSize()) {
        Box(modifier = Modifier.weight(1f)) {
            when (val screen = currentScreen) {
                is Screen.Dashboard -> DashboardScreen(
                    onBookingClick = { id -> currentScreen = Screen.BookingDetail(id) },
                    onPhoneBooking = { currentScreen = Screen.PhoneBooking },
                    onWhatsAppConfig = { currentScreen = Screen.WhatsAppConfig },
                    onCommission = { currentScreen = Screen.Commission },
                    onLogout = { isLoggedIn = false },
                    onUsers = { currentScreen = Screen.ActiveUsers },
                    onReports = { currentScreen = Screen.Reports },
                    onDriverConfig = { currentScreen = Screen.DriverConfig }
                )
                is Screen.Bookings -> BookingsScreen(
                    onBookingClick = { id -> currentScreen = Screen.BookingDetail(id) },
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.BookingDetail -> BookingDetailScreen(
                    bookingId = screen.id,
                    onBack = { currentScreen = Screen.Bookings },
                    onEditBooking = { id -> currentScreen = Screen.EditBooking(id) },
                    onDriverClick = { id -> currentScreen = Screen.DriverDetail(id) },
                    onUserClick = { phone, email, uid -> currentScreen = Screen.UserDetail(phone, email, uid) }
                )
                is Screen.EditBooking -> EditBookingScreen(
                    bookingId = screen.id,
                    onBack = { currentScreen = Screen.BookingDetail(screen.id) }
                )
                is Screen.Drivers -> DriversScreen(
                    onBack = { currentScreen = Screen.Dashboard },
                    onDriverClick = { id -> currentScreen = Screen.DriverDetail(id) }
                )
                is Screen.DriverDetail -> DriverDetailScreen(
                    driverId = screen.id,
                    onBack = { currentScreen = Screen.Drivers },
                    onBookingClick = { id -> currentScreen = Screen.BookingDetail(id) },
                    onUserClick = { phone, email, uid -> currentScreen = Screen.UserDetail(phone, email, uid) }
                )
                is Screen.Team -> TeamScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.Profile -> ProfileScreen(
                    onBack = { currentScreen = Screen.Dashboard },
                    onLogout = { isLoggedIn = false }
                )
                is Screen.PhoneBooking -> PhoneBookingScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.ActiveUsers -> ActiveUsersScreen(
                    onBack = { currentScreen = Screen.Dashboard },
                    onUserClick = { phone, email, userId -> currentScreen = Screen.UserDetail(phone, email, userId) }
                )
                is Screen.UserDetail -> UserDetailScreen(
                    phone = screen.phone,
                    email = screen.email,
                    userId = screen.userId,
                    onBack = { currentScreen = Screen.ActiveUsers },
                    onBookingClick = { id -> currentScreen = Screen.BookingDetail(id) },
                    onDriverClick = { id -> currentScreen = Screen.DriverDetail(id) }
                )
                is Screen.WhatsAppConfig -> WhatsAppConfigScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.DriverConfig -> DriverConfigScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.Commission -> CommissionScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
                is Screen.Reports -> ReportsScreen(
                    onBack = { currentScreen = Screen.Dashboard }
                )
            }
        }

        val hideNavScreens = listOf(Screen.BookingDetail(0).javaClass, Screen.EditBooking(0).javaClass, Screen.PhoneBooking.javaClass, Screen.WhatsAppConfig.javaClass, Screen.Commission.javaClass, Screen.Reports.javaClass, Screen.DriverDetail(0).javaClass, Screen.UserDetail("", "", 0).javaClass, Screen.DriverConfig.javaClass)
        if (hideNavScreens.none { it == currentScreen::class }) {
            NavigationBar(containerColor = DarkBgLighter) {
                val items = listOf(
                    Screen.Dashboard to Triple(Icons.Default.Dashboard, "Home", "Dashboard"),
                    Screen.Bookings to Triple(Icons.Default.ListAlt, "Bookings", "Bookings"),
                    Screen.Drivers to Triple(Icons.Default.DirectionsCar, "Drivers", "Drivers"),
                    Screen.Team to Triple(Icons.Default.Group, "Team", "Team"),
                    Screen.ActiveUsers to Triple(Icons.Default.People, "Users", "Users"),
                    Screen.Profile to Triple(Icons.Default.Person, "Profile", "Profile")
                )
                items.forEach { (target, triple) ->
                    val (icon, label, _) = triple
                    val selected = currentScreen::class == target::class
                    NavigationBarItem(
                        selected = selected,
                        onClick = { if (currentScreen::class != target::class) currentScreen = target },
                        icon = { Icon(icon, contentDescription = label) },
                        label = { Text(label, fontSize = 9.sp) },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = Gold, selectedTextColor = Gold,
                            unselectedIconColor = Gray500, unselectedTextColor = Gray500,
                            indicatorColor = Gold.copy(alpha = 0.12f)
                        )
                    )
                }
            }
        }
    }
}
