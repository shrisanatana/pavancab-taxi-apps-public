package com.pavancab.dispatch.ui.settings

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.*
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DriverConfigScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var loading by remember { mutableStateOf(true) }
    var saving by remember { mutableStateOf(false) }
    var subAmount by remember { mutableStateOf("") }
    var commissionAmount by remember { mutableStateOf("") }
    var successMsg by remember { mutableStateOf("") }
    var errorMsg by remember { mutableStateOf("") }

    LaunchedEffect(Unit) {
        try {
            val res = repo.getDriverConfig()
            subAmount = (res.get("driver_subscription_amount")?.asDouble ?: 1000.0).toInt().toString()
            commissionAmount = (res.get("driver_commission_per_ride")?.asDouble ?: 200.0).toInt().toString()
        } catch (_: Exception) {}
        loading = false
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Driver Payment Config", color = White, fontWeight = FontWeight.Bold) },
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
                modifier = Modifier.fillMaxSize().padding(padding).padding(16.dp).verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Text("CONFIGURE DRIVER PAYMENTS", color = Gray400, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 1.sp)

                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CardMembership, null, tint = Gold, modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Monthly Subscription Amount", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text("Drivers pay this amount per month for zero commission on rides", color = Gray400, fontSize = 12.sp)
                        Spacer(Modifier.height(12.dp))
                        OutlinedTextField(
                            value = subAmount,
                            onValueChange = { subAmount = it.filter { c -> c.isDigit() } },
                            label = { Text("Amount (INR)", fontSize = 12.sp) },
                            prefix = { Text("₹ ", color = Gold, fontWeight = FontWeight.Bold) },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold, unfocusedBorderColor = CardBorder,
                                focusedLabelColor = Gold, unfocusedLabelColor = Gray500,
                                cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )
                        Spacer(Modifier.height(4.dp))
                        Text("Default: ₹1000/month", color = Gray500, fontSize = 11.sp)
                    }
                }

                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.LocalTaxi, null, tint = Blue, modifier = Modifier.size(20.dp))
                            Spacer(Modifier.width(8.dp))
                            Text("Per-Ride Commission", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        }
                        Spacer(Modifier.height(4.dp))
                        Text("Drivers without subscription pay this per completed ride", color = Gray400, fontSize = 12.sp)
                        Spacer(Modifier.height(12.dp))
                        OutlinedTextField(
                            value = commissionAmount,
                            onValueChange = { commissionAmount = it.filter { c -> c.isDigit() } },
                            label = { Text("Amount (INR)", fontSize = 12.sp) },
                            prefix = { Text("₹ ", color = Blue, fontWeight = FontWeight.Bold) },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Blue, unfocusedBorderColor = CardBorder,
                                focusedLabelColor = Blue, unfocusedLabelColor = Gray500,
                                cursorColor = Blue, focusedTextColor = White, unfocusedTextColor = White
                            ),
                            modifier = Modifier.fillMaxWidth()
                        )
                        Spacer(Modifier.height(4.dp))
                        Text("Default: ₹200/ride", color = Gray500, fontSize = 11.sp)
                    }
                }

                Button(
                    onClick = {
                        saving = true
                        errorMsg = ""
                        successMsg = ""
                        scope.launch {
                            try {
                                val subAmt = subAmount.toIntOrNull() ?: 1000
                                val commAmt = commissionAmount.toIntOrNull() ?: 200
                                val res = repo.saveDriverConfig(subAmt, commAmt)
                                if (res.get("success")?.asBoolean == true) {
                                    successMsg = "Config saved! Subscription: ₹$subAmt/month, Commission: ₹$commAmt/ride"
                                } else {
                                    errorMsg = res.get("error")?.asString ?: "Save failed"
                                }
                            } catch (e: Exception) {
                                errorMsg = e.message ?: "Save failed"
                            }
                            saving = false
                        }
                    },
                    enabled = !saving && subAmount.isNotBlank() && commissionAmount.isNotBlank(),
                    colors = ButtonDefaults.buttonColors(containerColor = Gold),
                    shape = RoundedCornerShape(10.dp),
                    modifier = Modifier.fillMaxWidth().height(50.dp)
                ) {
                    if (saving) {
                        CircularProgressIndicator(modifier = Modifier.size(20.dp), color = DarkBg, strokeWidth = 2.dp)
                    } else {
                        Icon(Icons.Default.Save, null, tint = DarkBg, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("SAVE CONFIG", color = DarkBg, fontWeight = FontWeight.Bold, fontSize = 14.sp)
                    }
                }

                if (successMsg.isNotEmpty()) {
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Emerald.copy(alpha = 0.12f), border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.CheckCircle, null, tint = Emerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(successMsg, color = Emerald, fontSize = 12.sp)
                        }
                    }
                }

                if (errorMsg.isNotEmpty()) {
                    Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp), color = Red.copy(alpha = 0.12f), border = BorderStroke(1.dp, Red.copy(alpha = 0.3f))) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Error, null, tint = Red, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(8.dp))
                            Text(errorMsg, color = Red, fontSize = 12.sp)
                        }
                    }
                }

                Spacer(Modifier.height(20.dp))
            }
        }
    }
}
