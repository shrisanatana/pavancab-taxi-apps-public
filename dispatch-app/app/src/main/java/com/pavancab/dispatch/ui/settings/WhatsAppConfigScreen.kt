package com.pavancab.dispatch.ui.settings

import android.util.Log
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch

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
fun WhatsAppConfigScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var loading by remember { mutableStateOf(true) }
    var saving by remember { mutableStateOf(false) }
    var newToken by remember { mutableStateOf("") }
    var newPhoneId by remember { mutableStateOf("") }
    var currentTokenMasked by remember { mutableStateOf("") }
    var currentPhoneId by remember { mutableStateOf("") }
    var showToken by remember { mutableStateOf(false) }
    var statusMessage by remember { mutableStateOf("") }
    var errorMsg by remember { mutableStateOf("") }

    LaunchedEffect(Unit) {
        try {
            val config = repo.getWhatsAppConfig()
            Log.d("WACONFIG", "Response: $config")
            if (config.has("error")) {
                errorMsg = config.safeString("error") ?: "Unknown error"
            } else {
                currentTokenMasked = config.safeString("token_masked") ?: ""
                currentPhoneId = config.safeString("phone_id") ?: ""
            }
        } catch (e: Exception) {
            Log.e("WACONFIG", "Error: ${e.message}", e)
            errorMsg = "Failed to load: ${e.message}"
        }
        loading = false
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("WhatsApp API Config", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        if (loading) {
            Box(modifier = Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = Gold)
            }
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(horizontal = 16.dp)
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Spacer(Modifier.height(8.dp))

                // Current config status
                PavanCard {
                    SectionHeader("Current Configuration")
                    Spacer(Modifier.height(12.dp))

                    if (errorMsg.isNotBlank()) {
                        Surface(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(8.dp),
                            color = Red.copy(alpha = 0.12f)
                        ) {
                            Text(errorMsg, color = Red, fontSize = 12.sp, modifier = Modifier.padding(10.dp))
                        }
                    } else {
                        Text("API Token", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(2.dp))
                        if (currentTokenMasked.isNotBlank()) {
                            Text(currentTokenMasked, color = Emerald, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                        } else {
                            Text("Not configured (empty in database)", color = Orange, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                        }
                        Spacer(Modifier.height(10.dp))
                        Text("Phone Number ID", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(2.dp))
                        if (currentPhoneId.isNotBlank()) {
                            Text(currentPhoneId, color = Emerald, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                        } else {
                            Text("Not configured", color = Orange, fontSize = 13.sp, fontWeight = FontWeight.Medium)
                        }
                    }
                }

                // Update form
                PavanCard {
                    SectionHeader("Update Credentials")
                    Spacer(Modifier.height(12.dp))

                    Text("Enter new values below. Leave blank to keep current.", color = Gray500, fontSize = 11.sp)
                    Spacer(Modifier.height(12.dp))

                    Text("WhatsApp Business API Token", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    OutlinedTextField(
                        value = newToken,
                        onValueChange = { newToken = it },
                        placeholder = { Text("Paste new token...", color = Gray600, fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true,
                        visualTransformation = if (showToken) VisualTransformation.None else PasswordVisualTransformation(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = White, unfocusedTextColor = White,
                            focusedBorderColor = Gold, unfocusedBorderColor = CardBorder,
                            focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter
                        )
                    )
                    TextButton(onClick = { showToken = !showToken }) {
                        Text(if (showToken) "Hide" else "Show", color = Gold, fontSize = 11.sp)
                    }

                    Spacer(Modifier.height(8.dp))

                    Text("WhatsApp Phone Number ID", color = Gold, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    Spacer(Modifier.height(4.dp))
                    OutlinedTextField(
                        value = newPhoneId,
                        onValueChange = { newPhoneId = it },
                        placeholder = { Text("e.g. 711799862018733", color = Gray600, fontSize = 12.sp) },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        singleLine = true,
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedTextColor = White, unfocusedTextColor = White,
                            focusedBorderColor = Gold, unfocusedBorderColor = CardBorder,
                            focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter
                        )
                    )
                }

                Button(
                    onClick = {
                        if (newToken.isBlank() && newPhoneId.isBlank()) {
                            Toast.makeText(context, "Enter at least one value to update", Toast.LENGTH_SHORT).show()
                            return@Button
                        }
                        saving = true; statusMessage = ""
                        scope.launch {
                            try {
                                val r = repo.saveWhatsAppConfig(newToken, newPhoneId)
                                val success = r.safeBool("success") ?: false
                                statusMessage = if (success) "Updated successfully!" else r.safeString("error") ?: "Failed"
                                if (success) {
                                    newToken = ""; newPhoneId = ""
                                    val config = repo.getWhatsAppConfig()
                                    currentTokenMasked = config.safeString("token_masked") ?: ""
                                    currentPhoneId = config.safeString("phone_id") ?: ""
                                }
                            } catch (e: Exception) {
                                statusMessage = "Error: ${e.message}"
                            }
                            saving = false
                        }
                    },
                    modifier = Modifier.fillMaxWidth().height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Gold),
                    enabled = !saving
                ) {
                    if (saving) {
                        CircularProgressIndicator(modifier = Modifier.size(18.dp), color = DarkBg, strokeWidth = 2.dp)
                        Spacer(Modifier.width(8.dp))
                    }
                    Text("SAVE CREDENTIALS", fontWeight = FontWeight.Black, fontSize = 12.sp, color = DarkBg)
                }

                if (statusMessage.isNotBlank()) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        color = if (statusMessage.contains("success", true)) Emerald.copy(alpha = 0.12f) else Red.copy(alpha = 0.12f)
                    ) {
                        Text(
                            statusMessage,
                            color = if (statusMessage.contains("success", true)) Emerald else Red,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.padding(12.dp)
                        )
                    }
                }

                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
