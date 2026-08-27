package com.pavancab.driver.ui.profile

import android.content.Intent
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import coil.compose.AsyncImage
import com.pavancab.driver.BuildConfig
import com.pavancab.driver.data.Repository
import com.pavancab.driver.data.UserPrefs
import com.pavancab.driver.ui.components.*
import com.pavancab.driver.ui.theme.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream

@Composable
fun ProfileScreen(
    repo: Repository,
    onLogout: () -> Unit,
    onSubscription: () -> Unit = {},
    onWallet: () -> Unit = {},
    refreshTrigger: Int = 0
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var carModel by remember { mutableStateOf("") }
    var plateNumber by remember { mutableStateOf("") }
    var rating by remember { mutableStateOf(5.0) }
    var totalRatings by remember { mutableIntStateOf(0) }
    var photoUrl by remember { mutableStateOf("") }
    var isPremium by remember { mutableStateOf(false) }
    var subEnds by remember { mutableStateOf("") }
    var walletBalance by remember { mutableDoubleStateOf(0.0) }
    var loading by remember { mutableStateOf(true) }
    var uploadingPhoto by remember { mutableStateOf(false) }
    var savingVehicle by remember { mutableStateOf(false) }
    var showLogoutDialog by remember { mutableStateOf(false) }
    var editMode by remember { mutableStateOf(false) }
    val contactPhone = "+918180951176"

    suspend fun load() {
        name = UserPrefs.getName(context)
        phone = UserPrefs.getPhone(context)
        carModel = UserPrefs.getCarModel(context)
        plateNumber = UserPrefs.getPlateNumber(context)
        try {
            val profile = repo.getProfile()
            val driver = profile.getAsJsonObject("driver")
            if (driver != null) {
                rating = driver.get("rating")?.asDouble ?: 5.0
                totalRatings = driver.get("total_ratings")?.asInt ?: 0
                photoUrl = try { driver.get("profile_image")?.asString ?: "" } catch (_: Exception) { "" }
                if (name.isBlank()) name = try { driver.get("name")?.asString ?: "" } catch (_: Exception) { "" }
            }
        } catch (_: Exception) {}
        try {
            val sub = repo.getSubscriptionStatus()
            isPremium = sub.get("is_subscribed")?.asBoolean == true || sub.get("has_active_subscription")?.asBoolean == true
            subEnds = try { sub.get("end_date")?.asString ?: "" } catch (_: Exception) { "" }
        } catch (_: Exception) {}
        try {
            val w = repo.getWallet()
            walletBalance = w.get("balance")?.asDouble ?: 0.0
        } catch (_: Exception) {}
        loading = false
    }

    LaunchedEffect(Unit) {
        load()
        while (true) { delay(15000); load() }
    }
    LaunchedEffect(refreshTrigger) { if (refreshTrigger > 0) load() }

    // Photo picker
    val photoPicker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri: Uri? ->
        if (uri != null) {
            scope.launch {
                uploadingPhoto = true
                try {
                    val base64 = withContext(Dispatchers.IO) {
                        val bmp = context.contentResolver.openInputStream(uri)?.use { BitmapFactory.decodeStream(it) }
                            ?: throw Exception("Cannot read image")
                        // Downscale to max 640px
                        val maxSide = 640
                        val scale = Math.max(1, Math.max(bmp.width, bmp.height) / maxSide)
                        val scaled = if (scale > 1) Bitmap.createScaledBitmap(bmp, bmp.width / scale, bmp.height / scale, true) else bmp
                        val bos = ByteArrayOutputStream()
                        scaled.compress(Bitmap.CompressFormat.JPEG, 80, bos)
                        "data:image/jpeg;base64," + android.util.Base64.encodeToString(bos.toByteArray(), android.util.Base64.NO_WRAP)
                    }
                    val r = repo.uploadAvatar(base64)
                    if (r.get("success")?.asBoolean == true) {
                        photoUrl = try { r.get("photo_url")?.asString ?: photoUrl } catch (_: Exception) { photoUrl }
                    }
                } catch (_: Exception) {}
                uploadingPhoto = false
            }
        }
    }

    Column(
        modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp)
    ) {
        Surface(modifier = Modifier.fillMaxWidth(), color = DarkBgLighter, shape = RoundedCornerShape(bottomStart = 20.dp, bottomEnd = 20.dp)) {
            Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Text("PROFILE", color = White, fontSize = 18.sp, fontWeight = FontWeight.Black, modifier = Modifier.weight(1f))
                if (isPremium) {
                    Surface(shape = RoundedCornerShape(8.dp), color = Gold.copy(alpha = 0.2f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f))) {
                        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.WorkspacePremium, null, tint = Gold, modifier = Modifier.size(14.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("PREMIUM", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Black)
                        }
                    }
                }
            }
        }

        Spacer(Modifier.height(16.dp))

        // Driver profile card with photo
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), color = CardBg, border = BorderStroke(if (isPremium) 2.dp else 1.dp, if (isPremium) Gold.copy(alpha = 0.55f) else CardBorder)) {
            Column(modifier = Modifier.padding(20.dp), horizontalAlignment = Alignment.CenterHorizontally) {
                Box(contentAlignment = Alignment.BottomEnd) {
                    Surface(
                        modifier = Modifier.size(88.dp),
                        shape = RoundedCornerShape(44.dp),
                        color = Gold.copy(alpha = 0.12f),
                        border = BorderStroke(2.dp, if (isPremium) Gold else Gold.copy(alpha = 0.3f))
                    ) {
                        if (photoUrl.isNotBlank()) {
                            AsyncImage(
                                model = photoUrl,
                                contentDescription = "Profile photo",
                                modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(44.dp)),
                                contentScale = ContentScale.Crop
                            )
                        } else {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(Icons.Default.Person, null, tint = Gold, modifier = Modifier.size(40.dp))
                            }
                        }
                    }
                    // Camera edit button
                    Surface(
                        modifier = Modifier.size(28.dp).clip(RoundedCornerShape(14.dp)).clickable { photoPicker.launch("image/*") },
                        shape = RoundedCornerShape(14.dp),
                        color = Gold
                    ) {
                        Box(contentAlignment = Alignment.Center) {
                            Icon(Icons.Default.PhotoCamera, null, tint = DarkBg, modifier = Modifier.size(15.dp))
                        }
                    }
                }
                Spacer(Modifier.height(4.dp))
                Text("Tap camera to change photo", color = Gray600, fontSize = 9.sp)
                Spacer(Modifier.height(12.dp))
                Text(name.ifBlank { "Driver" }, color = White, fontSize = 20.sp, fontWeight = FontWeight.Black)
                Spacer(Modifier.height(4.dp))
                Text(phone, color = Gray400, fontSize = 14.sp)
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Star, null, tint = Gold, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(String.format("%.1f", rating), color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    Text(" ($totalRatings ratings)", color = Gray400, fontSize = 12.sp)
                }
                if (isPremium && subEnds.isNotBlank()) {
                    Spacer(Modifier.height(8.dp))
                    Text("Premium member until $subEnds \u2022 Zero commission", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                }
            }
        }

        Spacer(Modifier.height(12.dp))

        // Wallet card
        Surface(
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { onWallet() },
            color = Emerald.copy(alpha = 0.07f),
            border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f)),
            shape = RoundedCornerShape(14.dp)
        ) {
            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.AccountBalanceWallet, null, tint = Emerald, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(10.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text("Wallet", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    Text("Deposit money, view history", color = Gray400, fontSize = 11.sp)
                }
                Text("\u20B9${walletBalance.toInt()}", color = Emerald, fontSize = 18.sp, fontWeight = FontWeight.Black)
                Spacer(Modifier.width(6.dp))
                Icon(Icons.Default.ChevronRight, null, tint = Gray500)
            }
        }

        Spacer(Modifier.height(12.dp))

        // Name & Vehicle info (read-only view with edit pencil -> edit mode)
        Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("NAME & VEHICLE", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                    if (!editMode) {
                        Surface(
                            modifier = Modifier.size(30.dp).clip(RoundedCornerShape(15.dp)).clickable { editMode = true },
                            shape = RoundedCornerShape(15.dp),
                            color = Gold.copy(alpha = 0.15f),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.4f))
                        ) {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(Icons.Default.Edit, null, tint = Gold, modifier = Modifier.size(15.dp))
                            }
                        }
                    }
                }

                if (editMode) {
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(
                        value = name,
                        onValueChange = { name = it },
                        label = { Text("Display Name", color = Gray500, fontSize = 13.sp) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter, cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White)
                    )
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(
                        value = carModel,
                        onValueChange = { carModel = it },
                        label = { Text("Vehicle Model", color = Gray500, fontSize = 13.sp) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter, cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White)
                    )
                    Spacer(Modifier.height(10.dp))
                    OutlinedTextField(
                        value = plateNumber,
                        onValueChange = { plateNumber = it },
                        label = { Text("Number Plate", color = Gray500, fontSize = 13.sp) },
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = DarkBgLighter, unfocusedContainerColor = DarkBgLighter, cursorColor = Gold, focusedTextColor = White, unfocusedTextColor = White)
                    )
                    Spacer(Modifier.height(12.dp))
                    Button(
                        onClick = {
                            scope.launch {
                                savingVehicle = true
                                try {
                                    val r = repo.updateProfile(name, carModel, plateNumber)
                                    if (r.get("success")?.asBoolean == true) {
                                        UserPrefs.saveCarAndPlate(context, name, carModel, plateNumber)
                                        editMode = false
                                        Toast.makeText(context, "Profile saved", Toast.LENGTH_SHORT).show()
                                    } else {
                                        val err = try { r.get("error")?.asString } catch (_: Exception) { null } ?: "Failed to save"
                                        Toast.makeText(context, err, Toast.LENGTH_SHORT).show()
                                    }
                                } catch (_: Exception) {
                                    Toast.makeText(context, "Failed to save. Try again.", Toast.LENGTH_SHORT).show()
                                }
                                savingVehicle = false
                            }
                        },
                        modifier = Modifier.fillMaxWidth().height(46.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = DarkBg),
                        enabled = !savingVehicle
                    ) {
                        if (savingVehicle) CircularProgressIndicator(color = DarkBg, strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                        else { Icon(Icons.Default.Save, null, modifier = Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("SAVE PROFILE", fontWeight = FontWeight.Black) }
                    }
                    Spacer(Modifier.height(8.dp))
                    OutlinedButton(
                        onClick = {
                            scope.launch {
                                name = UserPrefs.getName(context)
                                carModel = UserPrefs.getCarModel(context)
                                plateNumber = UserPrefs.getPlateNumber(context)
                                editMode = false
                            }
                        },
                        modifier = Modifier.fillMaxWidth().height(44.dp),
                        shape = RoundedCornerShape(12.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Gray400)
                    ) { Text("CANCEL", fontWeight = FontWeight.Black, fontSize = 11.sp) }
                } else {
                    Spacer(Modifier.height(8.dp))
                    InfoRow("Display Name", name.ifBlank { "Driver" })
                    InfoRow("Vehicle", carModel.ifBlank { "-" })
                    InfoRow("Number Plate", plateNumber.ifBlank { "-" })
                }
            }
        }

        Spacer(Modifier.height(24.dp))

        // Subscription & Payments
        Surface(
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { onSubscription() },
            color = Gold.copy(alpha = 0.08f),
            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
        ) {
            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.CardMembership, null, tint = Gold, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(10.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text("Subscription & Payments", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                    Text(if (isPremium) "Manage your premium plan" else "Go premium \u2014 zero commission & early ride alerts", color = Gray400, fontSize = 11.sp)
                }
                Icon(Icons.Default.ChevronRight, null, tint = Gray500)
            }
        }

        Spacer(Modifier.height(12.dp))

        // Contact Us
        Surface(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(14.dp),
            color = CardBg,
            border = BorderStroke(1.dp, CardBorder)
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Headset, null, tint = Gold, modifier = Modifier.size(20.dp))
                    Spacer(Modifier.width(10.dp))
                    Column {
                        Text("Contact Admin", color = White, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        Text("For any queries or support", color = Gray400, fontSize = 11.sp)
                    }
                }
                Spacer(Modifier.height(12.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Surface(
                        modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable {
                            try {
                                context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:$contactPhone")))
                            } catch (_: Exception) {}
                        },
                        shape = RoundedCornerShape(12.dp),
                        color = Emerald.copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))
                    ) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                            Icon(Icons.Default.Phone, null, tint = Emerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("CALL", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Surface(
                        modifier = Modifier.weight(1f).clip(RoundedCornerShape(12.dp)).clickable {
                            try {
                                context.startActivity(
                                    Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/8180951176"))
                                )
                            } catch (_: Exception) {}
                        },
                        shape = RoundedCornerShape(12.dp),
                        color = Emerald.copy(alpha = 0.12f),
                        border = BorderStroke(1.dp, Emerald.copy(alpha = 0.3f))
                    ) {
                        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                            Icon(Icons.Default.Chat, null, tint = Emerald, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text("WHATSAPP", color = Emerald, fontSize = 12.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
                Spacer(Modifier.height(10.dp))
                Surface(shape = RoundedCornerShape(10.dp), color = Gold.copy(alpha = 0.08f), border = BorderStroke(1.dp, Gold.copy(alpha = 0.25f))) {
                    Row(modifier = Modifier.fillMaxWidth().padding(12.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                        Icon(Icons.Default.Call, null, tint = Gold, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("+91 81809 51176", color = Gold, fontSize = 16.sp, fontWeight = FontWeight.Black)
                    }
                }
            }
        }

        Spacer(Modifier.height(12.dp))

        // Logout
        Surface(
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)),
            color = Red.copy(alpha = 0.08f),
            border = BorderStroke(1.dp, Red.copy(alpha = 0.3f))
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text("App Info", color = Gray400, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(4.dp))
                Text("Version ${BuildConfig.VERSION_NAME}", color = Gray500, fontSize = 12.sp)
                Spacer(Modifier.height(12.dp))
                Button(
                    onClick = { showLogoutDialog = true },
                    modifier = Modifier.fillMaxWidth().height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Red, contentColor = White)
                ) {
                    Icon(Icons.Default.Logout, null, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("LOGOUT", fontWeight = FontWeight.Black)
                }
            }
        }
    }

    if (uploadingPhoto || loading) LoadingOverlay(if (uploadingPhoto) "Uploading photo..." else "Loading...")

    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            containerColor = DarkBgLighter,
            title = { Text("Logout?", color = White, fontWeight = FontWeight.Bold) },
            text = { Text("Are you sure you want to logout from this driver account?", color = Gray300) },
            confirmButton = {
                Button(onClick = {
                    showLogoutDialog = false
                    scope.launch {
                        try { repo.logout() } catch (_: Exception) {}
                        UserPrefs.clear(context)
                        onLogout()
                    }
                }, colors = ButtonDefaults.buttonColors(containerColor = Red), shape = RoundedCornerShape(10.dp)) {
                    Text("LOGOUT", color = White, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showLogoutDialog = false }) { Text("CANCEL", color = Gray400) }
            }
        )
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(modifier = Modifier.padding(vertical = 4.dp)) {
        Text(label, color = Gray400, fontSize = 13.sp, modifier = Modifier.width(80.dp))
        Text(value, color = White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
    }
}
