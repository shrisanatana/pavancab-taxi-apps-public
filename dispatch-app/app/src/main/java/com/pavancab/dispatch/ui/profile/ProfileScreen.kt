package com.pavancab.dispatch.ui.profile

import android.content.Intent
import android.media.RingtoneManager
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.BuildConfig
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch

private data class RingtoneOption(val name: String, val uri: Uri)

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(onBack: () -> Unit, onLogout: () -> Unit, onReports: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var role by remember { mutableStateOf("") }
    var ringtoneNew by remember { mutableStateOf("") }
    var ringtonePhone by remember { mutableStateOf("") }
    var ringtoneCancelled by remember { mutableStateOf("") }
    var ringtoneRepeat by remember { mutableIntStateOf(3) }
    var showRingtonePicker by remember { mutableStateOf(false) }
    var pickerTarget by remember { mutableStateOf("new") }
    var savingProfile by remember { mutableStateOf(false) }

    val ringtoneOptions = remember {
        val list = mutableListOf<RingtoneOption>()
        list.add(RingtoneOption("Default System Tone", RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)))
        list.add(RingtoneOption("Silent (No Sound)", Uri.EMPTY))
        try {
            val cursor = context.contentResolver.query(
                Uri.parse("content://media/internal/audio/media"),
                arrayOf("_id", "_display_name"),
                "is_notification != 0 OR is_alarm != 0",
                null, "_display_name ASC"
            )
            cursor?.use { c ->
                val idIdx = c.getColumnIndex("_id")
                val nameIdx = c.getColumnIndex("_display_name")
                var count = 0
                while (c.moveToNext() && count < 6) {
                    val id = c.getLong(idIdx)
                    val n = c.getString(nameIdx) ?: continue
                    val cleanName = n.replace(".ogg", "").replace(".mp3", "").replace(".wav", "").replace("_", " ").trim()
                    list.add(RingtoneOption(cleanName, Uri.parse("content://media/internal/audio/media/$id")))
                    count++
                }
            }
        } catch (_: Exception) {}
        list
    }

    LaunchedEffect(Unit) {
        name = UserPrefs.getName(context); phone = UserPrefs.getPhone(context)
        email = UserPrefs.getEmail(context); role = UserPrefs.getRole(context)
        ringtoneNew = UserPrefs.getRingtoneUri(context)
        ringtonePhone = UserPrefs.getRingtoneUriPhone(context)
        ringtoneCancelled = UserPrefs.getRingtoneUriCancelled(context)
        ringtoneRepeat = UserPrefs.getRingtoneRepeat(context)
    }

    fun toast(msg: String) = Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
    fun currentToneName(uri: String): String {
        if (uri.isBlank() || uri == Uri.EMPTY.toString()) return "Silent (No Sound)"
        return ringtoneOptions.find { it.uri.toString() == uri }?.name
            ?: RingtoneManager.getRingtone(context, Uri.parse(uri))?.getTitle(context)
            ?: "Custom Tone"
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Profile", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        LazyColumn(
            modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(vertical = 12.dp)
        ) {
            item {
                Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                    Surface(modifier = Modifier.size(80.dp), shape = RoundedCornerShape(20.dp), color = Gold.copy(alpha = 0.15f), border = BorderStroke(2.dp, Gold.copy(alpha = 0.4f))) {
                        Box(contentAlignment = Alignment.Center) { Icon(Icons.Default.Person, null, tint = Gold, modifier = Modifier.size(36.dp)) }
                    }
                }
            }
            item { Text(name.ifBlank { "Admin" }, color = White, fontSize = 22.sp, fontWeight = FontWeight.Bold, modifier = Modifier.fillMaxWidth(), textAlign = androidx.compose.ui.text.style.TextAlign.Center) }
            item {
                PavanCard {
                    SectionHeader("Account Info"); Spacer(Modifier.height(10.dp))
                    PavanTextField(value = name, onValueChange = { name = it }, label = "Name", leadingIcon = Icons.Default.Person)
                    Spacer(Modifier.height(8.dp))
                    PavanTextField(value = email, onValueChange = { email = it }, label = "Email", leadingIcon = Icons.Default.Email)
                    Spacer(Modifier.height(8.dp))
                    InfoRow("Phone", phone, Icons.Default.Phone)
                    InfoRow("Role", role.uppercase(), Icons.Default.Security, valueColor = if (role == "admin") Gold else Blue)
                    Spacer(Modifier.height(12.dp))
                    GradientButton(
                        onClick = {
                            if (name.isBlank()) { toast("Name cannot be empty"); return@GradientButton }
                            savingProfile = true
                            scope.launch {
                                val r = repo.updateProfile(name.trim(), email.trim())
                                savingProfile = false
                                if (r.safeBool("success") == true) {
                                    UserPrefs.saveName(context, name.trim())
                                    UserPrefs.saveEmail(context, email.trim())
                                    toast("Profile updated!")
                                } else {
                                    toast(r.safeString("error") ?: "Failed to update profile")
                                }
                            }
                        },
                        text = if (savingProfile) "Saving..." else "SAVE PROFILE",
                        icon = Icons.Default.Save,
                        enabled = !savingProfile
                    )
                }
            }
            // Notification Settings with 3 ringtone pickers
            item {
                PavanCard {
                    SectionHeader("Notification Settings"); Spacer(Modifier.height(10.dp))

                    // New Booking tone
                    Text("New Booking Tone", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    RingtonePickerRow(currentToneName(ringtoneNew)) { pickerTarget = "new"; showRingtonePicker = true }
                    Spacer(Modifier.height(12.dp))

                    // Phone Booking tone
                    Text("Phone Booking Tone", color = Blue, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    RingtonePickerRow(currentToneName(ringtonePhone)) { pickerTarget = "phone"; showRingtonePicker = true }
                    Spacer(Modifier.height(12.dp))

                    // Cancelled Booking tone
                    Text("Cancelled Booking Tone", color = Red, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    RingtonePickerRow(currentToneName(ringtoneCancelled)) { pickerTarget = "cancelled"; showRingtonePicker = true }
                    Spacer(Modifier.height(12.dp))

                    // Repeat count
                    Text("Alert Ring Times", color = Gray400, fontSize = 12.sp)
                    Spacer(Modifier.height(6.dp))
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        listOf(1, 2, 3, 5, 7).forEach { count ->
                            val selected = ringtoneRepeat == count
                            Surface(
                                modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable {
                                    ringtoneRepeat = count; scope.launch { UserPrefs.saveRingtoneRepeat(context, count) }
                                },
                                shape = RoundedCornerShape(10.dp),
                                color = if (selected) Gold else DarkBgLighter,
                                border = BorderStroke(1.dp, if (selected) Gold else CardBorder)
                            ) {
                                Text("${count}x", color = if (selected) DarkBg else Gray400, fontSize = 13.sp, fontWeight = FontWeight.Black, textAlign = androidx.compose.ui.text.style.TextAlign.Center, modifier = Modifier.fillMaxWidth().padding(vertical = 10.dp))
                            }
                        }
                    }
                }
            }
            item {
                PavanCard {
                    SectionHeader("Quick Actions"); Spacer(Modifier.height(10.dp))
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable {
                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/918180951176")))
                    }, shape = RoundedCornerShape(10.dp), color = Color(0xFF25D366).copy(alpha = 0.12f), border = BorderStroke(1.dp, Color(0xFF25D366).copy(alpha = 0.4f))) {
                        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Chat, null, tint = Color(0xFF25D366), modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(10.dp))
                            Column(Modifier.weight(1f)) { Text("WhatsApp Support", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold); Text("+91 81809 51176", color = Color(0xFF25D366), fontSize = 11.sp) }
                        }
                    }
                    Spacer(Modifier.height(8.dp))
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable {
                        context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://pavancab.com")))
                    }, shape = RoundedCornerShape(10.dp), color = Blue.copy(alpha = 0.12f), border = BorderStroke(1.dp, Blue.copy(alpha = 0.4f))) {
                        Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Language, null, tint = Blue, modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(10.dp))
                            Column(Modifier.weight(1f)) { Text("PavanCab Website", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold); Text("pavancab.com", color = Blue, fontSize = 11.sp) }
                        }
                    }
                    if (role == "admin") {
                        Spacer(Modifier.height(8.dp))
                        Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { onReports() }, shape = RoundedCornerShape(10.dp), color = Orange.copy(alpha = 0.12f), border = BorderStroke(1.dp, Orange.copy(alpha = 0.4f))) {
                            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.Report, null, tint = Orange, modifier = Modifier.size(20.dp))
                                Spacer(Modifier.width(10.dp))
                                Column(Modifier.weight(1f)) { Text("Ride Reports", color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold); Text("Review user-reported ride issues", color = Orange, fontSize = 11.sp) }
                            }
                        }
                    }
                }
            }
            item { Spacer(Modifier.height(8.dp)); GradientButton(onClick = { scope.launch { repo.logout(); UserPrefs.clear(context); com.pavancab.dispatch.network.ApiClient.cookieJar.clear() }; onLogout() }, text = "LOGOUT", icon = Icons.Default.Logout) }
            item {
                Text("PavanCab Dispatch v${BuildConfig.VERSION_NAME}", color = Gray500, fontSize = 11.sp, modifier = Modifier.fillMaxWidth(), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
            }
            item { Spacer(Modifier.height(16.dp)) }
        }
    }

    if (showRingtonePicker) {
        AlertDialog(
            onDismissRequest = { showRingtonePicker = false },
            containerColor = DarkBgLighter,
            title = { Text("Select Tone", color = White, fontWeight = FontWeight.Bold, fontSize = 16.sp) },
            text = {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    items(ringtoneOptions.size) { index ->
                        val opt = ringtoneOptions[index]
                        val currentUri = when (pickerTarget) { "phone" -> ringtonePhone; "cancelled" -> ringtoneCancelled; else -> ringtoneNew }
                        val isSelected = if (currentUri.isBlank()) opt.uri == Uri.EMPTY else opt.uri.toString() == currentUri
                        Surface(
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(8.dp)).clickable {
                                val uriStr = opt.uri.toString()
                                scope.launch {
                                    when (pickerTarget) {
                                        "phone" -> UserPrefs.saveRingtoneUriPhone(context, uriStr)
                                        "cancelled" -> UserPrefs.saveRingtoneUriCancelled(context, uriStr)
                                        else -> UserPrefs.saveRingtoneUri(context, uriStr)
                                    }
                                }
                                when (pickerTarget) { "phone" -> ringtonePhone = uriStr; "cancelled" -> ringtoneCancelled = uriStr; else -> ringtoneNew = uriStr }
                                showRingtonePicker = false
                                if (opt.uri != Uri.EMPTY) { try { RingtoneManager.getRingtone(context, opt.uri)?.play() } catch (_: Exception) {} }
                            },
                            shape = RoundedCornerShape(8.dp),
                            color = if (isSelected) Gold.copy(alpha = 0.15f) else Color.Transparent
                        ) {
                            Row(modifier = Modifier.padding(10.dp), verticalAlignment = Alignment.CenterVertically) {
                                if (isSelected) Icon(Icons.Default.CheckCircle, null, tint = Gold, modifier = Modifier.size(18.dp)) else Icon(Icons.Default.RadioButtonUnchecked, null, tint = Gray500, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(10.dp))
                                Text(opt.name, color = if (isSelected) Gold else White, fontSize = 13.sp, fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal)
                            }
                        }
                    }
                }
            },
            confirmButton = {},
            dismissButton = { TextButton(onClick = { showRingtonePicker = false }) { Text("Done", color = Gold) } }
        )
    }
}

@Composable
private fun RingtonePickerRow(toneName: String, onClick: () -> Unit) {
    Surface(
        modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable(onClick = onClick),
        shape = RoundedCornerShape(10.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)
    ) {
        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.MusicNote, null, tint = Gold, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(10.dp))
            Text(toneName, color = White, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.weight(1f))
            Icon(Icons.Default.ChevronRight, null, tint = Gray500, modifier = Modifier.size(16.dp))
        }
    }
}
