package com.pavancab.dispatch.ui.auth

import android.widget.Toast
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
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.CrashLogger
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.network.ApiClient
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private fun JsonObject?.safeString(key: String): String? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asString
}

private fun JsonObject?.safeBool(key: String): Boolean? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asBoolean
}

private fun JsonObject?.safeInt(key: String): Int? {
    val v = this?.get(key) ?: return null
    return if (v is JsonNull) null else v.asInt
}

@Composable
fun AuthScreen(onLoginSuccess: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var phone by remember { mutableStateOf("") }
    var otp by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var step by remember { mutableIntStateOf(0) }
    var loading by remember { mutableStateOf(false) }
    var countdown by remember { mutableIntStateOf(0) }
    var errorText by remember { mutableStateOf("") }
    var countryCode by remember { mutableStateOf("+91") }
    var showCountryPicker by remember { mutableStateOf(false) }
    var hasPassword by remember { mutableStateOf(false) }
    var flowType by remember { mutableStateOf("login") }

    LaunchedEffect(step) {
        if (step == 1 || step == 4) {
            countdown = 60
            while (countdown > 0) { delay(1000); countdown-- }
        }
    }

    fun fullPhone() = "$countryCode$phone"

    fun sendOtpForLogin() {
        errorText = ""; loading = true
        scope.launch {
            try {
                val res = ApiClient.api.sendOtp(fullPhone())
                loading = false
                if (res.safeBool("success") == true) {
                    if (flowType == "forgot_password") { step = 4 } else { step = 1 }
                } else {
                    errorText = res.safeString("message") ?: "Failed to send OTP"
                }
            } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(
            modifier = Modifier.fillMaxSize().padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(60.dp))
            Icon(Icons.Default.AdminPanelSettings, null, tint = Gold, modifier = Modifier.size(64.dp))
            Spacer(Modifier.height(16.dp))
            Text("PAVANCAB", color = Gold, fontSize = 24.sp, fontWeight = FontWeight.Black, letterSpacing = 3.sp)
            Text("DISPATCH", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold, letterSpacing = 2.sp)
            Spacer(Modifier.height(4.dp))
            when (step) {
                0 -> Text("Enter your phone number", color = Gray400, fontSize = 13.sp)
                1 -> Text("Enter OTP sent to WhatsApp", color = Gray400, fontSize = 13.sp)
                2 -> Text("Enter your password", color = Gray400, fontSize = 13.sp)
                3 -> Text("Set a password for quick login", color = Gray400, fontSize = 13.sp)
                4 -> Text("Enter OTP to reset password", color = Gray400, fontSize = 13.sp)
                5 -> Text("Set your new password", color = Gray400, fontSize = 13.sp)
            }

            Spacer(Modifier.height(40.dp))

            when (step) {
                0 -> {
                    Text("Enter your phone number", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        CountryCodePicker(selectedCode = countryCode, onCodeSelected = { countryCode = it }, modifier = Modifier.width(100.dp))
                        PavanTextField(value = phone, onValueChange = { if (it.length <= 10 && it.all { c -> c.isDigit() }) phone = it }, label = "Phone Number", leadingIcon = Icons.Default.Phone, modifier = Modifier.weight(1f))
                    }
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        errorText = ""; loading = true
                        scope.launch {
                            try {
                                val res = ApiClient.rawPost("api/dispatch.php", mapOf("action" to "check_phone", "phone" to fullPhone()))
                                loading = false
                                val success = res.safeBool("success") ?: false
                                if (success) {
                                    hasPassword = res.safeBool("has_password") ?: false
                                    if (hasPassword) { step = 2; password = "" }
                                    else { sendOtpForLogin() }
                                } else { sendOtpForLogin() }
                            } catch (e: Exception) { sendOtpForLogin() }
                        }
                    }, text = "CONTINUE", icon = Icons.Default.Send, enabled = phone.length >= 7 && !loading)
                }

                1 -> {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        IconButton(onClick = { step = 0; otp = ""; errorText = "" }) { Icon(Icons.Default.ArrowBack, "Back", tint = White) }
                        Text("Enter OTP", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    }
                    Text("Sent to ${fullPhone()}", color = Gray400, fontSize = 13.sp, modifier = Modifier.padding(start = 48.dp))
                    Spacer(Modifier.height(20.dp))
                    PavanTextField(value = otp, onValueChange = { if (it.length <= 6 && it.all { c -> c.isDigit() }) otp = it }, label = "6-Digit OTP", leadingIcon = Icons.Default.Lock)
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        errorText = ""; loading = true
                        scope.launch {
                            try {
                                val fcm = UserPrefs.getFcmToken(context)
                                val res = ApiClient.api.verifyOtp(phone = fullPhone(), otp = otp, fcmToken = fcm)
                                loading = false
                                val userObj = try { res.getAsJsonObject("user") } catch (_: Exception) { null }
                                val roleStr = userObj?.safeString("role") ?: ""
                                val isAuth = res.safeBool("isAdmin") == true ||
                                        res.safeBool("isTeam") == true ||
                                        roleStr in listOf("admin", "team", "owner")
                                if (!isAuth && res.safeBool("success") != true) {
                                    Toast.makeText(context, res.safeString("error") ?: "Login failed", Toast.LENGTH_LONG).show()
                                    return@launch
                                }
                                if (!isAuth) {
                                    Toast.makeText(context, "This app is for admin and team members only", Toast.LENGTH_LONG).show()
                                    return@launch
                                }
                                val user = res.getAsJsonObject("user")
                                if (user != null) {
                                    UserPrefs.saveLogin(context,
                                        user.safeInt("id") ?: 0, user.safeString("name") ?: "",
                                        user.safeString("mobile") ?: fullPhone(),
                                        user.safeString("email") ?: "",
                                        user.safeString("role") ?: "team",
                                        user.safeBool("isAdmin") ?: false,
                                        user.safeBool("isTeam") ?: false)
                                    UserPrefs.saveSession(context, ApiClient.cookieJar.getSessionId())
                                    if (fcm.isNotBlank()) UserPrefs.saveFcmToken(context, fcm)
                                }
                                val respHasPassword = res.safeBool("has_password") ?: false
                                if (respHasPassword) { onLoginSuccess() }
                                else { step = 3; password = ""; confirmPassword = "" }
                            } catch (e: Exception) {
                                loading = false
                                Toast.makeText(context, ApiClient.humanError(e), Toast.LENGTH_SHORT).show()
                            }
                        }
                    }, text = "VERIFY", icon = Icons.Default.CheckCircle, enabled = otp.length == 6 && !loading)

                    Spacer(Modifier.height(16.dp))
                    if (countdown > 0) {
                        Text("Resend OTP in ${countdown}s", color = Gray500, fontSize = 13.sp)
                    } else {
                        Text("Resend OTP", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.clickable {
                            scope.launch {
                                try {
                                    ApiClient.api.sendOtp(fullPhone())
                                    countdown = 60
                                    Toast.makeText(context, "OTP resent!", Toast.LENGTH_SHORT).show()
                                } catch (_: Exception) {}
                            }
                        })
                    }
                }

                2 -> {
                    Text("Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                    PavanTextField(value = password, onValueChange = { password = it }, label = "Enter password", leadingIcon = Icons.Default.Lock)
                    Spacer(Modifier.height(8.dp))
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                        TextButton(onClick = { flowType = "forgot_password"; sendOtpForLogin() }) {
                            Text("Forgot Password?", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Spacer(Modifier.height(12.dp))
                    GradientButton(onClick = {
                        errorText = ""; loading = true
                        scope.launch {
                            try {
                                val fcm = UserPrefs.getFcmToken(context)
                                val res = ApiClient.rawPost("api/dispatch.php", mapOf("action" to "login_with_password", "phone" to fullPhone(), "password" to password, "fcm_token" to fcm))
                                loading = false
                                val ok = res.safeBool("success") ?: false
                                if (ok) {
                                    val isAuth = res.safeBool("isAdmin") == true || res.safeBool("isTeam") == true
                                    if (!isAuth) {
                                        Toast.makeText(context, "This app is for admin and team members only", Toast.LENGTH_LONG).show()
                                        return@launch
                                    }
                                    val user = try { res.getAsJsonObject("user") } catch (_: Exception) { null }
                                    if (user != null) {
                                        UserPrefs.saveLogin(context,
                                            user.safeInt("id") ?: 0, user.safeString("name") ?: "",
                                            user.safeString("mobile") ?: fullPhone(),
                                            user.safeString("email") ?: "",
                                            user.safeString("role") ?: "team",
                                            user.safeBool("isAdmin") ?: false,
                                            user.safeBool("isTeam") ?: false)
                                        UserPrefs.saveSession(context, ApiClient.cookieJar.getSessionId())
                                        if (fcm.isNotBlank()) UserPrefs.saveFcmToken(context, fcm)
                                    }
                                    onLoginSuccess()
                                } else {
                                    errorText = res.safeString("error") ?: "Login failed"
                                }
                            } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
                        }
                    }, text = "LOGIN", icon = Icons.Default.CheckCircle, enabled = password.length >= 4 && !loading)
                }

                3 -> {
                    Surface(shape = RoundedCornerShape(12.dp), color = Gold.copy(alpha = 0.1f), modifier = Modifier.fillMaxWidth()) {
                        Text("Set a password for quick login next time", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.SemiBold, modifier = Modifier.padding(12.dp))
                    }
                    Spacer(Modifier.height(16.dp))
                    Text("New Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    PavanTextField(value = password, onValueChange = { password = it }, label = "Min 4 characters", leadingIcon = Icons.Default.Lock)
                    Spacer(Modifier.height(12.dp))
                    Text("Confirm Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, placeholder = { Text("Re-enter password", color = Gray600) }, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                    if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 12.sp) }
                    Spacer(Modifier.height(20.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        OutlinedButton(onClick = { onLoginSuccess() }, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) { Text("SKIP", fontWeight = FontWeight.Black, fontSize = 12.sp) }
                        GradientButton(onClick = {
                            if (password.length >= 4 && password == confirmPassword) {
                                errorText = ""; loading = true
                                scope.launch {
                                    try {
                                        val res = ApiClient.rawPost("api/dispatch.php", mapOf("action" to "set_password", "phone" to fullPhone(), "password" to password))
                                        loading = false
                                        if (res.safeBool("success") == true) { onLoginSuccess() }
                                        else { errorText = res.safeString("error") ?: "Failed" }
                                    } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
                                }
                            } else { errorText = "Password must be at least 4 characters and match" }
                        }, text = "SET PASSWORD", icon = Icons.Default.CheckCircle, enabled = password.length >= 4 && password == confirmPassword && !loading)
                    }
                }

                4 -> {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        IconButton(onClick = { step = 2; otp = ""; errorText = "" }) { Icon(Icons.Default.ArrowBack, "Back", tint = White) }
                        Text("Reset Password", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                    }
                    Text("Sent to ${fullPhone()}", color = Gray400, fontSize = 13.sp, modifier = Modifier.padding(start = 48.dp))
                    Spacer(Modifier.height(20.dp))
                    PavanTextField(value = otp, onValueChange = { if (it.length <= 6 && it.all { c -> c.isDigit() }) otp = it }, label = "6-Digit OTP", leadingIcon = Icons.Default.Lock)
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = { step = 5 }, text = "NEXT", icon = Icons.Default.Send, enabled = otp.length == 6 && !loading)
                    Spacer(Modifier.height(16.dp))
                    if (countdown > 0) {
                        Text("Resend OTP in ${countdown}s", color = Gray500, fontSize = 13.sp)
                    } else {
                        Text("Resend OTP", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.clickable {
                            scope.launch {
                                try { ApiClient.api.sendOtp(fullPhone()); countdown = 60 } catch (_: Exception) {}
                            }
                        })
                    }
                }

                5 -> {
                    Text("New Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    PavanTextField(value = password, onValueChange = { password = it }, label = "Min 4 characters", leadingIcon = Icons.Default.Lock)
                    Spacer(Modifier.height(12.dp))
                    Text("Confirm Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), visualTransformation = PasswordVisualTransformation(), singleLine = true, placeholder = { Text("Re-enter password", color = Gray600) }, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, unfocusedContainerColor = Gray900, focusedContainerColor = Gray900, focusedTextColor = White, unfocusedTextColor = White, cursorColor = Gold))
                    if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 12.sp) }
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        if (password.length >= 4 && password == confirmPassword) {
                            errorText = ""; loading = true
                            scope.launch {
                                try {
                                    val res = ApiClient.rawPost("api/dispatch.php", mapOf("action" to "reset_password", "phone" to fullPhone(), "otp" to otp, "password" to password))
                                    loading = false
                                    if (res.safeBool("success") == true) {
                                        Toast.makeText(context, "Password reset! Login with password.", Toast.LENGTH_LONG).show()
                                        step = 0; password = ""; confirmPassword = ""; otp = ""; hasPassword = true; flowType = "login"
                                    } else { errorText = res.safeString("error") ?: "Reset failed" }
                                } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
                            }
                        } else { errorText = "Password must be at least 4 characters and match" }
                    }, text = "RESET PASSWORD", icon = Icons.Default.CheckCircle, enabled = password.length >= 4 && password == confirmPassword && !loading)
                }
            }
            if (errorText.isNotEmpty()) {
                Spacer(Modifier.height(12.dp))
                Text(errorText, color = Red, fontSize = 13.sp, textAlign = TextAlign.Center, modifier = Modifier.fillMaxWidth())
            }
        }
        if (loading) {
            Box(modifier = Modifier.fillMaxSize().background(DarkBg.copy(alpha = 0.85f)), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    CircularProgressIndicator(color = Gold, modifier = Modifier.size(48.dp), strokeWidth = 3.dp)
                    Spacer(Modifier.height(16.dp))
                    Text("Please wait...", color = Gray300, fontSize = 13.sp)
                }
            }
        }
    }
}
