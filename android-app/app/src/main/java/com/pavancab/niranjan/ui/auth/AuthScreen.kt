package com.pavancab.niranjan.ui.auth

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChatBubble
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Send
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.gson.JsonObject
import com.pavancab.niranjan.CrashLogger
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import com.pavancab.niranjan.network.ApiClient
import com.pavancab.niranjan.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

data class CountryCode(val code: String, val name: String)

val countryCodes = listOf(
    CountryCode("+91", "India"), CountryCode("+44", "United Kingdom"), CountryCode("+1", "USA / Canada"),
    CountryCode("+971", "UAE"), CountryCode("+7", "Russia"), CountryCode("+49", "Germany"),
    CountryCode("+61", "Australia"), CountryCode("+33", "France"), CountryCode("+65", "Singapore"),
    CountryCode("+966", "Saudi Arabia"), CountryCode("+974", "Qatar"), CountryCode("+968", "Oman"),
    CountryCode("+965", "Kuwait"), CountryCode("+973", "Bahrain"), CountryCode("+66", "Thailand"),
    CountryCode("+60", "Malaysia"), CountryCode("+39", "Italy"), CountryCode("+34", "Spain"),
    CountryCode("+31", "Netherlands"), CountryCode("+41", "Switzerland"), CountryCode("+972", "Israel"),
    CountryCode("+81", "Japan"), CountryCode("+86", "China"), CountryCode("+880", "Bangladesh"),
    CountryCode("+977", "Nepal"), CountryCode("+94", "Sri Lanka")
)

private fun optString(obj: JsonObject, key: String): String? {
    return try { val el = obj.get(key); if (el != null && !el.isJsonNull) el.asString else null } catch (_: Exception) { null }
}
private fun optBool(obj: JsonObject, key: String): Boolean? {
    return try { val el = obj.get(key); if (el != null && !el.isJsonNull) el.asBoolean else null } catch (_: Exception) { null }
}
private fun extractDevOtp(resp: JsonObject): String? {
    return optString(resp, "dev_tp") ?: optString(resp, "dev_otp")
}

@Composable
fun AuthScreen(onLoginSuccess: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var step by remember { mutableIntStateOf(0) }
    var flowType by remember { mutableStateOf("login") }
    var phone by remember { mutableStateOf("") }
    var otp by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var savedName by remember { mutableStateOf("") }
    var name by remember { mutableStateOf("") }
    var countryCode by remember { mutableStateOf("+91") }
    var showCountryPicker by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }

    // Post-login profile setup for first-time users (backend created account with blank name)
    var showProfileSetup by remember { mutableStateOf(false) }
    var setupName by remember { mutableStateOf("") }
    var setupEmail by remember { mutableStateOf("") }
    var setupSaving by remember { mutableStateOf(false) }

    fun finishLogin() {
        val saved = runCatching { kotlinx.coroutines.runBlocking { UserPrefs.getName(context) } }.getOrDefault("")
        if (saved.isBlank() || saved == "Rider") {
            setupName = ""
            showProfileSetup = true
        } else onLoginSuccess()
    }
    var message by remember { mutableStateOf("") }
    var isError by remember { mutableStateOf(false) }
    var devOtp by remember { mutableStateOf<String?>(null) }
    var resendSeconds by remember { mutableIntStateOf(0) }
    var resending by remember { mutableStateOf(false) }
    var hasPassword by remember { mutableStateOf(false) }
    var userNameFromServer by remember { mutableStateOf("") }

    LaunchedEffect(Unit) {
        val existing = UserPrefs.getName(context)
        if (existing.isNotBlank()) { savedName = existing; name = existing }
    }
    LaunchedEffect(resendSeconds) {
        if (resendSeconds > 0) { delay(1000); resendSeconds-- }
    }

    fun fullPhone() = "$countryCode$phone"

    fun sendOtpForLogin() {
        loading = true; message = ""; isError = false
        scope.launch {
            try {
                val resp = repo.sendOtp(fullPhone(), appType = "passenger")
                val ok = optBool(resp, "success") ?: false
                if (ok) {
                    message = optString(resp, "message") ?: "OTP sent on WhatsApp!"
                    isError = false; devOtp = extractDevOtp(resp)
                    step = 1; resendSeconds = 60; otp = ""
                } else {
                    message = optString(resp, "error") ?: "Could not send OTP"; isError = true
                }
            } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
            loading = false
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
            Spacer(Modifier.height(48.dp))
            Box(modifier = Modifier.size(64.dp).clip(RoundedCornerShape(20.dp)).background(Brush.linearGradient(listOf(Emerald.copy(alpha = 0.2f), Gold.copy(alpha = 0.2f), Emerald.copy(alpha = 0.1f)))), contentAlignment = Alignment.Center) {
                Icon(Icons.Default.ChatBubble, contentDescription = null, tint = Emerald, modifier = Modifier.size(32.dp))
            }
            Spacer(Modifier.height(16.dp))
            Text("PAVANCAB", fontSize = 24.sp, fontWeight = FontWeight.Black, letterSpacing = 3.sp, color = White)
            Text("Goa Taxi Service", fontSize = 12.sp, color = Gray400)
            Spacer(Modifier.height(8.dp))
            when (step) {
                0 -> Text("Enter your phone number to continue", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
                1 -> Text("Enter the 6-digit OTP sent to WhatsApp", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
                2 -> Text("Enter your password to login", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
                3 -> Text("Set a password for quick login next time", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
                4 -> Text("Enter OTP to reset your password", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
                5 -> Text("Set your new password", fontSize = 12.sp, color = Gray400, textAlign = TextAlign.Center)
            }

            if (message.isNotEmpty()) { Spacer(Modifier.height(12.dp)); Text(message, color = if (isError) Red else Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center) }
            if (devOtp != null) { Spacer(Modifier.height(8.dp)); Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.15f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.4f))) { Text("Development OTP: $devOtp", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Black, textAlign = TextAlign.Center, modifier = Modifier.padding(12.dp).fillMaxWidth()) } }
            Spacer(Modifier.height(24.dp))

            when (step) {
                0 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("WhatsApp Mobile Number", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            Surface(modifier = Modifier.width(112.dp).clip(RoundedCornerShape(12.dp)).clickable { showCountryPicker = !showCountryPicker }, shape = RoundedCornerShape(12.dp), color = Gray900, border = BorderStroke(1.dp, if (showCountryPicker) Gold else Gray700)) {
                                Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 17.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Text(countryCode, color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                    Spacer(Modifier.weight(1f))
                                    Icon(Icons.Default.ArrowDropDown, contentDescription = null, tint = Gray400, modifier = Modifier.size(20.dp))
                                }
                            }
                            OutlinedTextField(value = phone, onValueChange = { phone = it.filter { c -> c.isDigit() } }, placeholder = { Text("9876543210", color = Gray600) }, modifier = Modifier.weight(1f), shape = RoundedCornerShape(12.dp), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        }
                        AnimatedVisibility(visible = showCountryPicker) {
                            Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), color = Gray900, border = BorderStroke(1.dp, CardBorder)) {
                                Column(modifier = Modifier.heightIn(max = 280.dp).verticalScroll(rememberScrollState()).padding(vertical = 4.dp)) {
                                    countryCodes.forEach { cc ->
                                        Row(modifier = Modifier.fillMaxWidth().clickable { countryCode = cc.code; showCountryPicker = false }.padding(horizontal = 16.dp, vertical = 11.dp), verticalAlignment = Alignment.CenterVertically) {
                                            Text(cc.code, color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                            Spacer(Modifier.width(12.dp))
                                            Text(cc.name, color = Gray400, fontSize = 13.sp)
                                            Spacer(Modifier.weight(1f))
                                            if (cc.code == countryCode) { Icon(Icons.Default.Check, contentDescription = null, tint = Gold, modifier = Modifier.size(18.dp)) }
                                        }
                                    }
                                }
                            }
                        }
                        Button(onClick = {
                            if (phone.length >= 7) {
                                loading = true; message = ""; isError = false
                                scope.launch {
                                    try {
                                        val resp = ApiClient.rawPost("api/passenger.php", mapOf("action" to "check_phone", "phone" to fullPhone()))
                                        loading = false
                                        val success = optBool(resp, "success") ?: false
                                        if (success) {
                                            hasPassword = optBool(resp, "has_password") ?: false
                                            userNameFromServer = optString(resp, "name") ?: ""
                                            if (hasPassword) {
                                                step = 2; password = ""
                                            } else {
                                                sendOtpForLogin()
                                            }
                                        } else {
                                            sendOtpForLogin()
                                        }
                                    } catch (e: Exception) {
                                        sendOtpForLogin()
                                    }
                                }
                            } else { message = "Enter a valid mobile number"; isError = true }
                        }, enabled = phone.length >= 7 && !loading, modifier = Modifier.fillMaxWidth().height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                            if (loading) { CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp) } else { Text("CONTINUE", fontWeight = FontWeight.Black, fontSize = 12.sp, letterSpacing = 1.sp) }
                        }
                    }
                }

                1 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("Enter 6-Digit OTP sent to ${fullPhone()}", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = otp, onValueChange = { otp = it.filter { c -> c.isDigit() }.take(6) }, placeholder = { Text("123456", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gold.copy(alpha = 0.6f), focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = Gold, unfocusedTextColor = Gold, cursorColor = Gold))
                        if (savedName.isNotBlank()) {
                            Surface(shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.1f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                                Row(Modifier.padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Check, null, tint = Emerald, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(8.dp))
                                    Text("Welcome back, $savedName!", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { step = 0; otp = ""; message = ""; isError = false }, enabled = !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("BACK", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = {
                                if (otp.length == 6) {
                                    loading = true; message = ""; isError = false
                                    scope.launch {
                                        try {
                                            val resp = repo.verifyOtp(fullPhone(), otp, name.trim())
                                            val ok = optBool(resp, "success") ?: false
                                            if (ok) {
                                                val user = try { if (resp.has("user") && resp.get("user").isJsonObject) resp.getAsJsonObject("user") else null } catch (_: Exception) { null }
                                                val userId = try { user?.get("id")?.asInt ?: 0 } catch (_: Exception) { 0 }
                                                val userName = optString(user ?: JsonObject(), "name")?.takeIf { it.isNotBlank() } ?: name.trim().ifBlank { "Rider" }
                                                val userPhone = optString(user ?: JsonObject(), "mobile")?.takeIf { it.isNotBlank() } ?: fullPhone()
                                                val userEmail = optString(user ?: JsonObject(), "email") ?: ""
                                                val userRole = optString(user ?: JsonObject(), "role")?.takeIf { it.isNotBlank() } ?: "user"
                                                val isAdmin = optBool(resp, "isAdmin") ?: false
                                                val isTeam = optBool(resp, "isTeam") ?: false
                                                val respHasPassword = optBool(resp, "has_password") ?: false
                                                UserPrefs.saveUser(context, userId, userName, userPhone, userEmail, userRole, isAdmin, isTeam)
                                                val rTok = optString(resp, "remember_token") ?: ""
                                                if (rTok.isNotBlank()) UserPrefs.saveAutoToken(context, rTok)
                                                val sessId = ApiClient.cookieJar.getSessionId()
                                                if (sessId.isNotEmpty()) UserPrefs.saveSessionId(context, sessId)
                                                try {
                                                    val fcmTok = UserPrefs.getFcmToken(context)
                                                    if (fcmTok.isNotEmpty()) Repository(context).saveFcmTokenToServer(fcmTok)
                                                } catch (_: Exception) {}
                                                if (respHasPassword) {
                                                    CrashLogger.log("INFO", "Login success: $userPhone", "AuthScreen")
                                                    finishLogin()
                                                } else {
                                                    step = 3; password = ""; confirmPassword = ""
                                                }
                                            } else {
                                                message = optString(resp, "error") ?: "Invalid OTP"; isError = true
                                            }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                        loading = false
                                    }
                                }
                            }, enabled = otp.length == 6 && !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                if (loading) { CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp) } else { Icon(Icons.Default.CheckCircle, contentDescription = null, modifier = Modifier.size(16.dp)); Spacer(Modifier.width(8.dp)); Text("VERIFY", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center, verticalAlignment = Alignment.CenterVertically) {
                            if (resendSeconds > 0) {
                                Text("Resend OTP in ${resendSeconds}s", color = Gray500, fontSize = 12.sp)
                            } else {
                                TextButton(onClick = {
                                    resending = true; scope.launch {
                                        try {
                                            val resp = repo.sendOtp(fullPhone(), appType = "passenger")
                                            val ok = optBool(resp, "success") ?: false
                                            if (ok) { resendSeconds = 60; message = "OTP resent!"; isError = false }
                                            else { message = optString(resp, "error") ?: "Failed to resend"; isError = true }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                        resending = false
                                    }
                                }, enabled = !resending) {
                                    if (resending) { CircularProgressIndicator(modifier = Modifier.size(14.dp), color = Gold, strokeWidth = 2.dp); Spacer(Modifier.width(6.dp)) }
                                    Text("RESEND OTP", color = Gold, fontWeight = FontWeight.Black, fontSize = 11.sp)
                                }
                            }
                        }
                    }
                }

                2 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        if (userNameFromServer.isNotBlank()) {
                            Surface(shape = RoundedCornerShape(12.dp), color = Emerald.copy(alpha = 0.1f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                                Row(Modifier.padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Check, null, tint = Emerald, modifier = Modifier.size(16.dp))
                                    Spacer(Modifier.width(8.dp))
                                    Text("Welcome back, $userNameFromServer!", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                                }
                            }
                        }
                        Text("Password", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = password, onValueChange = { password = it }, placeholder = { Text("Enter password", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                            TextButton(onClick = {
                                flowType = "forgot_password"
                                sendOtpForLogin()
                                step = 4
                            }) {
                                Text("Forgot Password?", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { step = 0; password = ""; message = ""; isError = false }, enabled = !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("BACK", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = {
                                if (password.length >= 4) {
                                    loading = true; message = ""; isError = false
                                    scope.launch {
                                        try {
                                            val fcm = UserPrefs.getFcmToken(context)
                                            val resp = ApiClient.rawPost("api/passenger.php", mapOf("action" to "login_with_password", "phone" to fullPhone(), "password" to password, "fcm_token" to fcm))
                                            loading = false
                                            val ok = optBool(resp, "success") ?: false
                                            if (ok) {
                                                val user = try { if (resp.has("user") && resp.get("user").isJsonObject) resp.getAsJsonObject("user") else null } catch (_: Exception) { null }
                                                val userId = try { user?.get("id")?.asInt ?: 0 } catch (_: Exception) { 0 }
                                                val userName = optString(user ?: JsonObject(), "name")?.takeIf { it.isNotBlank() } ?: "Rider"
                                                val userPhone = optString(user ?: JsonObject(), "mobile")?.takeIf { it.isNotBlank() } ?: fullPhone()
                                                val userEmail = optString(user ?: JsonObject(), "email") ?: ""
                                                val userRole = optString(user ?: JsonObject(), "role")?.takeIf { it.isNotBlank() } ?: "user"
                                                val isAdmin = optBool(resp, "isAdmin") ?: false
                                                val isTeam = optBool(resp, "isTeam") ?: false
                                                UserPrefs.saveUser(context, userId, userName, userPhone, userEmail, userRole, isAdmin, isTeam)
                                                val rTok = optString(resp, "remember_token") ?: ""
                                                if (rTok.isNotBlank()) UserPrefs.saveAutoToken(context, rTok)
                                                val sessId = ApiClient.cookieJar.getSessionId()
                                                if (sessId.isNotEmpty()) UserPrefs.saveSessionId(context, sessId)
                                                try {
                                                    val fcmTok = UserPrefs.getFcmToken(context)
                                                    if (fcmTok.isNotEmpty()) Repository(context).saveFcmTokenToServer(fcmTok)
                                                } catch (_: Exception) {}
                                                finishLogin()
                                            } else {
                                                message = optString(resp, "error") ?: "Login failed"; isError = true
                                            }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                    }
                                } else { message = "Enter your password"; isError = true }
                            }, enabled = password.length >= 4 && !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Emerald, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                if (loading) { CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp) } else { Text("LOGIN", fontWeight = FontWeight.Black, fontSize = 12.sp) }
                            }
                        }
                    }
                }

                3 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Surface(shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.1f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))) {
                            Row(Modifier.padding(horizontal = 14.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.Lock, null, tint = Gold, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Set a password for quick login next time (no OTP needed)", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.SemiBold)
                            }
                        }
                        Text("New Password", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = password, onValueChange = { password = it }, placeholder = { Text("Min 4 characters", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        Text("Confirm Password", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, placeholder = { Text("Re-enter password", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 11.sp) }
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { onLoginSuccess() }, enabled = !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("SKIP", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = {
                                if (password.length >= 4 && password == confirmPassword) {
                                    loading = true; message = ""; isError = false
                                    scope.launch {
                                        try {
                                            val resp = ApiClient.rawPost("api/passenger.php", mapOf("action" to "set_password", "phone" to fullPhone(), "password" to password))
                                            loading = false
                                            val ok = optBool(resp, "success") ?: false
                                            if (ok) {
                                                finishLogin()
                                            } else {
                                                message = optString(resp, "error") ?: "Failed to set password"; isError = true
                                            }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                    }
                                } else { message = "Password must be at least 4 characters and match"; isError = true }
                            }, enabled = password.length >= 4 && password == confirmPassword && !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                if (loading) { CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp) } else { Text("SET PASSWORD", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                            }
                        }
                    }
                }

                4 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("Enter 6-Digit OTP sent to ${fullPhone()}", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = otp, onValueChange = { otp = it.filter { c -> c.isDigit() }.take(6) }, placeholder = { Text("123456", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gold.copy(alpha = 0.6f), focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = Gold, unfocusedTextColor = Gold, cursorColor = Gold))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { step = 2; otp = ""; message = ""; isError = false }, enabled = !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("BACK", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = {
                                if (otp.length == 6) {
                                    step = 5
                                }
                            }, enabled = otp.length == 6 && !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                Text("NEXT", fontWeight = FontWeight.Black, fontSize = 12.sp)
                            }
                        }
                        Spacer(Modifier.height(4.dp))
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                            if (resendSeconds > 0) {
                                Text("Resend OTP in ${resendSeconds}s", color = Gray500, fontSize = 12.sp)
                            } else {
                                TextButton(onClick = {
                                    resending = true; scope.launch {
                                        try {
                                            val resp = repo.sendOtp(fullPhone(), appType = "passenger")
                                            if (optBool(resp, "success") == true) { resendSeconds = 60; message = "OTP resent!"; isError = false }
                                            else { message = optString(resp, "error") ?: "Failed"; isError = true }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                        resending = false
                                    }
                                }, enabled = !resending) {
                                    Text("RESEND OTP", color = Gold, fontWeight = FontWeight.Black, fontSize = 11.sp)
                                }
                            }
                        }
                    }
                }

                5 -> {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("New Password", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = password, onValueChange = { password = it }, placeholder = { Text("Min 4 characters", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        Text("Confirm Password", color = Gray300, fontSize = 12.sp, fontWeight = FontWeight.Black)
                        OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, placeholder = { Text("Re-enter password", color = Gray600) }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                        if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 11.sp) }
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            OutlinedButton(onClick = { step = 4; password = ""; confirmPassword = ""; message = ""; isError = false }, enabled = !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) {
                                Text("BACK", fontWeight = FontWeight.Black, fontSize = 11.sp)
                            }
                            Button(onClick = {
                                if (password.length >= 4 && password == confirmPassword) {
                                    loading = true; message = ""; isError = false
                                    scope.launch {
                                        try {
                                            val resp = ApiClient.rawPost("api/passenger.php", mapOf("action" to "reset_password", "phone" to fullPhone(), "otp" to otp, "password" to password))
                                            loading = false
                                            val ok = optBool(resp, "success") ?: false
                                            if (ok) {
                                                message = "Password reset successfully! Login with your password."; isError = false
                                                step = 0; password = ""; confirmPassword = ""; otp = ""; hasPassword = true
                                            } else {
                                                message = optString(resp, "error") ?: "Reset failed"; isError = true
                                            }
                                        } catch (e: Exception) { message = ApiClient.humanError(e); isError = true }
                                    }
                                } else { message = "Password must be at least 4 characters and match"; isError = true }
                            }, enabled = password.length >= 4 && password == confirmPassword && !loading, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg, disabledContainerColor = Gray700, disabledContentColor = Gray500)) {
                                if (loading) { CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp) } else { Text("RESET PASSWORD", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                            }
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
            Text("Need help? WhatsApp 8180951176", fontSize = 10.sp, color = Gray500)
        }
        if (loading) {
            Box(modifier = Modifier.fillMaxSize().background(DarkBg.copy(alpha = 0.6f)), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Gold)
            }
        }
    }

    // First-time profile setup — captures name/email so dispatch & drivers know who you are
    if (showProfileSetup) {
        AlertDialog(
            onDismissRequest = { },
            containerColor = DarkBgLighter,
            title = { Text("Welcome to PAVANCAB! \uD83C\uDF34", color = White, fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("Tell us your name so drivers can greet you properly.", color = Gray400, fontSize = 12.sp)
                    OutlinedTextField(value = setupName, onValueChange = { setupName = it.take(40) }, label = { Text("Your Name *") }, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(10.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold))
                    OutlinedTextField(value = setupEmail, onValueChange = { setupEmail = it.take(60) }, label = { Text("Email (optional, for receipts)") }, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(10.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = Gray900, unfocusedContainerColor = Gray900, cursorColor = Gold))
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        if (setupName.isBlank()) return@Button
                        setupSaving = true
                        scope.launch {
                            try { Repository(context).updateProfile(name = setupName.trim(), email = setupEmail.trim().ifBlank { null }) } catch (_: Exception) {}
                            val id = UserPrefs.getUserId(context); val ph = UserPrefs.getPhone(context)
                            val role = UserPrefs.getRole(context); val ia = UserPrefs.getIsAdmin(context); val it2 = UserPrefs.getIsTeam(context)
                            UserPrefs.saveUser(context, id, setupName.trim(), ph, setupEmail.trim(), role, ia, it2)
                            setupSaving = false
                            showProfileSetup = false
                            onLoginSuccess()
                        }
                    },
                    enabled = setupName.isNotBlank() && !setupSaving,
                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg)
                ) { Text("START EXPLORING", fontWeight = FontWeight.Black) }
            },
            dismissButton = { TextButton(onClick = { showProfileSetup = false; onLoginSuccess() }) { Text("Skip for now", color = Gray500) } }
        )
    }
}
