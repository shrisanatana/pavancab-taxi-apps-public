package com.pavancab.niranjan.ui.profile

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

private const val WHATSAPP_SUPPORT_URL = "https://wa.me/918180951176"
private const val WHATSAPP_SUPPORT_DISPLAY = "+91 81809 51176"
private val WhatsAppGreen = Color(0xFF25D366)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(onLoggedOut: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }

    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var role by remember { mutableStateOf("") }
    var showLogoutDialog by remember { mutableStateOf(false) }
    var editingName by remember { mutableStateOf(false) }
    var editingEmail by remember { mutableStateOf(false) }
    var editName by remember { mutableStateOf("") }
    var editEmail by remember { mutableStateOf("") }
    var saving by remember { mutableStateOf(false) }
    var saveMsg by remember { mutableStateOf("") }
    var isError by remember { mutableStateOf(false) }

    // Client-computed stats from rides already available to the app
    var totalRides by remember { mutableIntStateOf(0) }
    var completedRides by remember { mutableIntStateOf(0) }
    var todayRides by remember { mutableIntStateOf(0) }
    var tomorrowRides by remember { mutableIntStateOf(0) }

    LaunchedEffect(Unit) {
        name = UserPrefs.getName(context)
        phone = UserPrefs.getPhone(context)
        email = UserPrefs.getEmail(context)
        role = UserPrefs.getRole(context)
        try {
            val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.US)
            val todayStr = sdf.format(Calendar.getInstance().time)
            val cal = Calendar.getInstance(); cal.add(Calendar.DAY_OF_YEAR, 1)
            val tomorrowStr = sdf.format(cal.time)
            val list = repo.getBookings(UserPrefs.getPhone(context), UserPrefs.getEmail(context))
            totalRides = list.size
            completedRides = list.count { it.status.uppercase() == "COMPLETED" }
            todayRides = list.count { it.pickupDate.startsWith(todayStr) }
            tomorrowRides = list.count { it.pickupDate.startsWith(tomorrowStr) }
        } catch (_: Exception) {}
    }

    Scaffold(
        containerColor = DarkBg,
        topBar = {
            TopAppBar(
                title = { Text("PROFILE", fontWeight = FontWeight.Black, fontSize = 15.sp, letterSpacing = 1.sp, color = White) },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier.fillMaxSize().padding(padding).verticalScroll(rememberScrollState()).padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(16.dp))
            Box(modifier = Modifier.size(84.dp).clip(RoundedCornerShape(26.dp)).background(Brush.linearGradient(listOf(Emerald, Gold))).border(2.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(26.dp)), contentAlignment = Alignment.Center) {
                Text(name.firstOrNull()?.toString()?.uppercase() ?: "P", color = DarkBg, fontSize = 34.sp, fontWeight = FontWeight.Black)
            }
            Spacer(Modifier.height(14.dp))

            if (editingName) {
                Column(modifier = Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
                    OutlinedTextField(value = editName, onValueChange = { editName = it }, label = { Text("Name", color = Gray400, fontSize = 11.sp) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), singleLine = true, colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900))
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = { editingName = false }, shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) { Text("CANCEL", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                        Button(onClick = { if (editName.isNotBlank()) { saving = true; scope.launch { try { val r = repo.updateProfile(editName.trim(), null); saveMsg = "Name updated!"; isError = false; name = editName.trim(); val uid = UserPrefs.getUserId(context); val adm = UserPrefs.getIsAdmin(context); val team = UserPrefs.getIsTeam(context); UserPrefs.saveUser(context, uid, name, phone, email, role, adm, team) } catch (e: Exception) { saveMsg = "Failed: ${e.message}"; isError = true }; saving = false; editingName = false } } }, enabled = editName.isNotBlank() && !saving, shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)) { Text("SAVE", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                    }
                }
            } else {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(name.ifEmpty { "Guest" }, color = White, fontSize = 20.sp, fontWeight = FontWeight.Black)
                    Spacer(Modifier.width(6.dp))
                    // Proper 40dp touch target for the edit action
                    Box(modifier = Modifier.size(32.dp).clip(CircleShape).background(Gold.copy(alpha = 0.12f)).clickable { editName = name; editingName = true }, contentAlignment = Alignment.Center) {
                        Icon(Icons.Default.Edit, "Edit name", tint = Gold, modifier = Modifier.size(14.dp))
                    }
                }
            }

            Spacer(Modifier.height(4.dp))
            Text(phone, color = Gray400, fontSize = 13.sp)

            if (saveMsg.isNotEmpty()) {
                Spacer(Modifier.height(6.dp))
                LaunchedEffect(saveMsg) { delay(3000); saveMsg = "" }
                Surface(shape = RoundedCornerShape(6.dp), color = if (isError) Red.copy(alpha = 0.15f) else Emerald.copy(alpha = 0.15f)) {
                    Text(saveMsg, color = if (isError) Red else Emerald, fontSize = 11.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp))
                }
            }

            // Ride history stats — computed from data the app already has
            if (totalRides > 0) {
                Spacer(Modifier.height(18.dp))
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, Gold.copy(alpha = 0.35f))) {
                    Row(modifier = Modifier.padding(vertical = 14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("$totalRides", color = Gold, fontSize = 18.sp, fontWeight = FontWeight.Black)
                            Text("Total Rides", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        }
                        Box(Modifier.width(1.dp).height(30.dp).background(CardBorder))
                        Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("$completedRides", color = Emerald, fontSize = 18.sp, fontWeight = FontWeight.Black)
                            Text("Completed", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        }
                        Box(Modifier.width(1.dp).height(30.dp).background(CardBorder))
                        Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("$todayRides", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black)
                            Text("Today", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        }
                        Box(Modifier.width(1.dp).height(30.dp).background(CardBorder))
                        Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                            Text("$tomorrowRides", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black)
                            Text("Tomorrow", color = Gray500, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                        }
                    }
                }
            }

            Spacer(Modifier.height(24.dp))

            Card(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp), colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(12.dp), border = BorderStroke(1.dp, CardBorder)) {
                Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Phone, null, tint = Gray400, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text("Mobile", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black)
                        Text(phone, color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                    }
                }
            }

            if (editingEmail) {
                Card(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp), colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(12.dp), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                    Column(Modifier.padding(14.dp)) {
                        Text("EMAIL", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black)
                        Spacer(Modifier.height(6.dp))
                        OutlinedTextField(value = editEmail, onValueChange = { editEmail = it }, placeholder = { Text("you@email.com", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(8.dp), singleLine = true, colors = OutlinedTextFieldDefaults.colors(focusedTextColor = White, unfocusedTextColor = White, focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900))
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { editingEmail = false }, shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) { Text("CANCEL", fontWeight = FontWeight.Black, fontSize = 10.sp) }
                            Button(onClick = { saving = true; scope.launch { try { repo.updateProfile(null, editEmail.trim()); saveMsg = "Email updated!"; isError = false; email = editEmail.trim(); val uid = UserPrefs.getUserId(context); val adm = UserPrefs.getIsAdmin(context); val team = UserPrefs.getIsTeam(context); UserPrefs.saveUser(context, uid, name, phone, email, role, adm, team) } catch (e: Exception) { saveMsg = "Failed: ${e.message}"; isError = true }; saving = false; editingEmail = false } }, shape = RoundedCornerShape(8.dp), colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg)) { Text("SAVE", fontWeight = FontWeight.Black, fontSize = 10.sp) }
                        }
                    }
                }
            } else {
                Card(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp), colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(12.dp), border = BorderStroke(1.dp, CardBorder)) {
                    Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Email, null, tint = Gray400, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text("Email", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black)
                            Text(if (email.isNotEmpty()) email else "Not set", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                        Box(modifier = Modifier.size(32.dp).clip(CircleShape).background(Gold.copy(alpha = 0.12f)).clickable { editEmail = email; editingEmail = true }, contentAlignment = Alignment.Center) {
                            Icon(Icons.Default.Edit, "Edit email", tint = Gold, modifier = Modifier.size(14.dp))
                        }
                    }
                }
            }

            Spacer(Modifier.height(24.dp))

            Card(onClick = { runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(WHATSAPP_SUPPORT_URL))) } }, modifier = Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = CardBg), shape = RoundedCornerShape(12.dp), border = BorderStroke(1.dp, WhatsAppGreen.copy(alpha = 0.35f))) {
                Row(Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                    Box(modifier = Modifier.size(40.dp).clip(RoundedCornerShape(10.dp)).background(WhatsAppGreen.copy(alpha = 0.15f)), contentAlignment = Alignment.Center) {
                        Icon(Icons.Default.SupportAgent, null, tint = WhatsAppGreen, modifier = Modifier.size(20.dp))
                    }
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text("SUPPORT", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black, letterSpacing = 1.sp)
                        Text("Chat with our team on WhatsApp", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        Text(WHATSAPP_SUPPORT_DISPLAY, color = WhatsAppGreen, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    }
                    Icon(Icons.Default.OpenInNew, null, tint = Gray400, modifier = Modifier.size(16.dp))
                }
            }

            Spacer(Modifier.height(24.dp))

            Button(onClick = { showLogoutDialog = true }, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Red.copy(alpha = 0.15f), contentColor = Red)) {
                Icon(Icons.Default.Logout, null, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text("LOGOUT", fontWeight = FontWeight.Black, fontSize = 12.sp, letterSpacing = 1.sp)
            }

            Spacer(Modifier.height(16.dp))
            Text("PAVANCAB v${com.pavancab.niranjan.BuildConfig.VERSION_NAME}", color = Gray600, fontSize = 10.sp)
            Text("Goa Taxi Service", color = Gray700, fontSize = 10.sp)
            Spacer(Modifier.height(24.dp))
        }
    }

    if (showLogoutDialog) {
        AlertDialog(onDismissRequest = { showLogoutDialog = false }, title = { Text("Logout?", fontWeight = FontWeight.Black, color = White) }, text = { Text("Are you sure you want to logout?", color = Gray300) }, confirmButton = { Button(onClick = { showLogoutDialog = false; scope.launch { repo.logout(); UserPrefs.clearAutoToken(context); UserPrefs.clearUser(context); onLoggedOut() } }, colors = ButtonDefaults.buttonColors(containerColor = Red)) { Text("LOGOUT", fontWeight = FontWeight.Black) } }, dismissButton = { TextButton(onClick = { showLogoutDialog = false }) { Text("CANCEL", color = Gray400) } }, containerColor = CardBg, shape = RoundedCornerShape(16.dp))
    }
}
