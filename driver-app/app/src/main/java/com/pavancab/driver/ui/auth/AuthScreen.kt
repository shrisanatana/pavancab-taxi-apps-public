package com.pavancab.driver.ui.auth

import android.content.Intent
import android.net.Uri
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.pavancab.driver.CrashLogger
import com.pavancab.driver.data.UserPrefs
import com.pavancab.driver.network.ApiClient
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
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
fun AuthScreen(
    onLoginSuccess: () -> Unit,
    onNotApproved: (phone: String) -> Unit
) {
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
    var hasPassword by remember { mutableStateOf(false) }
    var flowType by remember { mutableStateOf("login") }

    LaunchedEffect(step) {
        if (step == 1 || step == 4) {
            countdown = 60
            while (countdown > 0) { delay(1000); countdown-- }
        }
    }

    fun fullPhone() = "$countryCode$phone"

    fun handleDriverOtpResult() {
        scope.launch {
            try {
                val fcm = UserPrefs.getFcmToken(context)
                val driverRes = ApiClient.rawPost("api/driver.php?action=verify-otp", mapOf(
                    "phone" to fullPhone(), "otp" to otp, "fcm_token" to fcm
                ))
                loading = false
                val driverSuccess = driverRes.safeBool("success") ?: false
                if (driverSuccess) {
                    val driver = driverRes.getAsJsonObject("driver")
                    if (driver != null) {
                        UserPrefs.saveLogin(context,
                            driver.get("id")?.asInt ?: 0,
                            driver.get("name")?.asString ?: "",
                            driver.get("phone")?.asString ?: fullPhone(),
                            driver.get("car_model")?.asString ?: "",
                            driver.get("plate_number")?.asString ?: "")
                        UserPrefs.saveSession(context, ApiClient.cookieJar.getSessionId())
                    }
                    val fcm = UserPrefs.getFcmToken(context)
                    if (fcm.isNotBlank()) {
                        UserPrefs.saveFcmToken(context, fcm)
ApiClient.rawPost("api/driver.php?action=save-fcm-token", mapOf("fcm_token" to fcm, "phone" to fullPhone()))
                    }
                    val respHasPassword = driverRes.safeBool("has_password") ?: false
                    if (respHasPassword) { onLoginSuccess() }
                    else { step = 3; password = ""; confirmPassword = "" }
                } else { errorText = driverRes.safeString("error") ?: "Driver account error" }
            } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
        }
    }

    fun sendOtpForLogin() {
        errorText = ""; loading = true
        scope.launch {
            try {
                val res = ApiClient.rawPost("api/driver.php?action=send_otp", mapOf("phone" to fullPhone(), "app_type" to "driver"))
                loading = false
                if (res.safeBool("success") == true) {
                    if (flowType == "forgot_password") { step = 4 } else { step = 1 }
                } else { errorText = res.safeString("message") ?: res.safeString("error") ?: "Failed to send OTP" }
            } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
        }
    }

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(
            modifier = Modifier.fillMaxSize().padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(80.dp))
            Icon(Icons.Default.LocalTaxi, null, tint = Gold, modifier = Modifier.size(72.dp))
            Spacer(Modifier.height(16.dp))
            Text("PAVANCAB", color = Gold, fontSize = 26.sp, fontWeight = FontWeight.Black, letterSpacing = 3.sp)
            Text("DRIVER", color = White, fontSize = 16.sp, fontWeight = FontWeight.Bold, letterSpacing = 2.sp)
            Spacer(Modifier.height(4.dp))
            when (step) {
                0 -> Text("Enter your phone number", color = Gray400, fontSize = 13.sp)
                1 -> Text("Enter OTP sent to WhatsApp", color = Gray400, fontSize = 13.sp)
                2 -> Text("Enter your password", color = Gray400, fontSize = 13.sp)
                3 -> Text("Set a password for quick login", color = Gray400, fontSize = 13.sp)
                4 -> Text("Enter OTP to reset password", color = Gray400, fontSize = 13.sp)
                5 -> Text("Set your new password", color = Gray400, fontSize = 13.sp)
            }

            Spacer(Modifier.height(48.dp))

            when (step) {
                0 -> {
                    Text("Enter your phone number", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedTextField(value = countryCode, onValueChange = {}, readOnly = true, modifier = Modifier.width(80.dp), shape = RoundedCornerShape(12.dp), singleLine = true, colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                        PavanTextField(value = phone, onValueChange = { if (it.length <= 10 && it.all { c -> c.isDigit() }) phone = it }, label = "Phone Number", leadingIcon = Icons.Default.Phone, modifier = Modifier.weight(1f))
                    }
                    Spacer(Modifier.height(8.dp))
                    Text("We'll send an OTP to your WhatsApp", color = Gray500, fontSize = 11.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        errorText = ""; loading = true
                        scope.launch {
                            try {
                                val res = ApiClient.rawPost("api/driver.php?action=check-phone", mapOf("phone" to fullPhone()))
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
                    OutlinedTextField(value = otp, onValueChange = { if (it.length <= 6 && it.all { c -> c.isDigit() }) otp = it }, label = { Text("6-Digit OTP") }, leadingIcon = { Icon(Icons.Default.Lock, null, tint = Gold) }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        errorText = ""; loading = true
                        scope.launch {
                            try {
                                handleDriverOtpResult()
                            } catch (e: Exception) { loading = false; errorText = ApiClient.humanError(e) }
                        }
                    }, text = "VERIFY & LOGIN", icon = Icons.Default.CheckCircle, enabled = otp.length == 6 && !loading)

                    Spacer(Modifier.height(16.dp))
                    if (countdown > 0) { Text("Resend OTP in ${countdown}s", color = Gray500, fontSize = 13.sp) }
                    else {
                        Text("Resend OTP", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.clickable {
                            scope.launch {
                                try { ApiClient.rawPost("api/driver.php?action=send_otp", mapOf("phone" to fullPhone(), "app_type" to "driver")); countdown = 60; Toast.makeText(context, "OTP resent!", Toast.LENGTH_SHORT).show() } catch (_: Exception) {}
                            }
                        })
                    }
                }

                2 -> {
                    Text("Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(12.dp))
                    OutlinedTextField(value = password, onValueChange = { password = it }, label = { Text("Enter password") }, leadingIcon = { Icon(Icons.Default.Lock, null, tint = Gold) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
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
                                val res = ApiClient.rawPost("api/driver.php?action=login-with-password", mapOf("phone" to fullPhone(), "password" to password))
                                loading = false
                                val ok = res.safeBool("success") ?: false
                                if (ok) {
                                    val driver = res.getAsJsonObject("driver")
                                    if (driver != null) {
                                        UserPrefs.saveLogin(context, driver.get("id")?.asInt ?: 0, driver.get("name")?.asString ?: "", driver.get("phone")?.asString ?: fullPhone(), driver.get("car_model")?.asString ?: "", driver.get("plate_number")?.asString ?: "")
                                        UserPrefs.saveSession(context, ApiClient.cookieJar.getSessionId())
                                    }
                                    if (fcm.isNotBlank()) { UserPrefs.saveFcmToken(context, fcm); ApiClient.rawPost("api/driver.php?action=save-fcm-token", mapOf("fcm_token" to fcm, "phone" to fullPhone())) }
                                    onLoginSuccess()
                                } else { errorText = res.safeString("error") ?: "Login failed" }
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
                    OutlinedTextField(value = password, onValueChange = { password = it }, label = { Text("Min 4 characters") }, leadingIcon = { Icon(Icons.Default.Lock, null, tint = Gold) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                    Spacer(Modifier.height(12.dp))
                    Text("Confirm Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), placeholder = { Text("Re-enter password", color = Gray600) }, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White))
                    if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 12.sp) }
                    Spacer(Modifier.height(20.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        OutlinedButton(onClick = { onLoginSuccess() }, modifier = Modifier.weight(1f).height(48.dp), shape = RoundedCornerShape(12.dp), colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)) { Text("SKIP", fontWeight = FontWeight.Black, fontSize = 12.sp) }
                        GradientButton(onClick = {
                            if (password.length >= 4 && password == confirmPassword) {
                                errorText = ""; loading = true
                                scope.launch {
                                    try {
                                        val res = ApiClient.rawPost("api/driver.php?action=set-password", mapOf("phone" to fullPhone(), "password" to password))
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
                    OutlinedTextField(value = otp, onValueChange = { if (it.length <= 6 && it.all { c -> c.isDigit() }) otp = it }, label = { Text("6-Digit OTP") }, leadingIcon = { Icon(Icons.Default.Lock, null, tint = Gold) }, singleLine = true, modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = { step = 5 }, text = "NEXT", icon = Icons.Default.Send, enabled = otp.length == 6 && !loading)
                    Spacer(Modifier.height(16.dp))
                    if (countdown > 0) { Text("Resend OTP in ${countdown}s", color = Gray500, fontSize = 13.sp) }
                    else {
                        Text("Resend OTP", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.clickable {
                            scope.launch {
                                try { ApiClient.rawPost("api/driver.php?action=send_otp", mapOf("phone" to fullPhone(), "app_type" to "driver")); countdown = 60 } catch (_: Exception) {}
                            }
                        })
                    }
                }

                5 -> {
                    Text("New Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(value = password, onValueChange = { password = it }, label = { Text("Min 4 characters") }, leadingIcon = { Icon(Icons.Default.Lock, null, tint = Gold) }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold))
                    Spacer(Modifier.height(12.dp))
                    Text("Confirm Password", color = Gray300, fontSize = 14.sp, modifier = Modifier.fillMaxWidth())
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(value = confirmPassword, onValueChange = { confirmPassword = it }, singleLine = true, visualTransformation = PasswordVisualTransformation(), modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp), placeholder = { Text("Re-enter password", color = Gray600) }, colors = OutlinedTextFieldDefaults.colors(unfocusedBorderColor = if (confirmPassword.isNotEmpty() && confirmPassword != password) Red else Gray700, focusedBorderColor = Gold, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White))
                    if (confirmPassword.isNotEmpty() && confirmPassword != password) { Text("Passwords don't match", color = Red, fontSize = 12.sp) }
                    Spacer(Modifier.height(20.dp))
                    GradientButton(onClick = {
                        if (password.length >= 4 && password == confirmPassword) {
                            errorText = ""; loading = true
                            scope.launch {
                                try {
                                    val res = ApiClient.rawPost("api/driver.php?action=reset-password", mapOf("phone" to fullPhone(), "otp" to otp, "password" to password))
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

@Composable
fun NotApprovedScreen(phone: String, onBack: () -> Unit, revoked: Boolean = false) {
    val context = LocalContext.current
    val whatsappPhone = "919518541625"
    val title = if (revoked) "Account Revoked" else "Account Pending"
    val message = if (revoked)
        "Your driver account access has been revoked by dispatch.\nYou cannot accept or offer fares.\nPlease contact the admin to get re-approved."
    else
        "Your driver account is pending approval.\nPlease contact us on WhatsApp to get approved."

    Box(modifier = Modifier.fillMaxSize().background(DarkBg)) {
        Column(
            modifier = Modifier.fillMaxSize().padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Surface(
                modifier = Modifier.size(88.dp),
                shape = RoundedCornerShape(44.dp),
                color = Orange.copy(alpha = 0.12f),
                border = androidx.compose.foundation.BorderStroke(2.dp, Orange.copy(alpha = 0.3f))
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(if (revoked) Icons.Default.Lock else Icons.Default.Warning, null, tint = Orange, modifier = Modifier.size(40.dp))
                }
            }
            Spacer(Modifier.height(24.dp))
            Text(title, color = White, fontSize = 22.sp, fontWeight = FontWeight.Black)
            Spacer(Modifier.height(8.dp))
            Text(
                message,
                color = Gray300, fontSize = 14.sp, textAlign = TextAlign.Center, lineHeight = 22.sp
            )
            Spacer(Modifier.height(8.dp))
            Text(phone, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold)
            Spacer(Modifier.height(32.dp))

            Surface(
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable {
                    try {
                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/$whatsappPhone?text=Hi%2C%20I%20registered%20as%20a%20driver%20on%20PAVANCAB.%20Please%20check%20my%20account%20approval%20status.%0APhone%3A%20$phone"))
                        context.startActivity(intent)
                    } catch (_: Exception) {}
                },
                shape = RoundedCornerShape(14.dp),
                color = Emerald.copy(alpha = 0.12f),
                border = androidx.compose.foundation.BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))
            ) {
                Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.Default.Chat, null, tint = Emerald, modifier = Modifier.size(22.dp))
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text("CONTACT ON WHATSAPP", color = Emerald, fontSize = 14.sp, fontWeight = FontWeight.Black)
                        Text("+91 95185 41625", color = Gray300, fontSize = 12.sp)
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            Surface(
                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { onBack() },
                shape = RoundedCornerShape(14.dp),
                color = CardBg,
                border = androidx.compose.foundation.BorderStroke(1.dp, CardBorder)
            ) {
                Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.Default.Logout, null, tint = Gray400, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("LOG OUT", color = Gray400, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}
