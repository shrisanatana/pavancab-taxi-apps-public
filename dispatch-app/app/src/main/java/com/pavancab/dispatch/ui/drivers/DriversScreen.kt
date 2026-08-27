package com.pavancab.dispatch.ui.drivers

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.model.Driver
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
fun DriversScreen(onBack: () -> Unit, onDriverClick: (Int) -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var drivers by remember { mutableStateOf<List<Driver>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var filter by remember { mutableStateOf("") }
    var searchQuery by remember { mutableStateOf("") }
    var menuOpenFor by remember { mutableStateOf<Driver?>(null) }
    var showAddDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf<Driver?>(null) }
    var showEditDialog by remember { mutableStateOf<Driver?>(null) }
    var dName by remember { mutableStateOf("") }
    var dPhone by remember { mutableStateOf("") }
    var dCar by remember { mutableStateOf("") }
    var dPlate by remember { mutableStateOf("") }
    var isAdmin by remember { mutableStateOf(false) }

    var eName by remember { mutableStateOf("") }
    var ePhone by remember { mutableStateOf("") }
    var eCar by remember { mutableStateOf("") }
    var ePlate by remember { mutableStateOf("") }

    suspend fun refresh() { drivers = repo.getDrivers(filter); loading = false }
    LaunchedEffect(Unit) { isAdmin = UserPrefs.isAdmin(context); refresh() }
    LaunchedEffect(filter) { loading = true; refresh() }

    // Auto-refresh driver online/availability status while the screen is visible
    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    LaunchedEffect(filter) {
        while (true) {
            kotlinx.coroutines.delay(10000)
            if (lifecycleOwner.lifecycle.currentState.isAtLeast(androidx.lifecycle.Lifecycle.State.STARTED)) refresh()
        }
    }

    val filteredDrivers = remember(drivers, searchQuery) {
        if (searchQuery.isBlank()) drivers else drivers.filter { d ->
            d.name.contains(searchQuery, ignoreCase = true) ||
            d.phone.contains(searchQuery, ignoreCase = true) ||
            d.carModel.contains(searchQuery, ignoreCase = true) ||
            d.plateNumber.contains(searchQuery, ignoreCase = true)
        }
    }

    fun toast(msg: String) = Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Drivers", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = { IconButton(onClick = { showAddDialog = true }) { Icon(Icons.Default.PersonAdd, "Add", tint = Gold) } },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            OutlinedTextField(
                value = searchQuery, onValueChange = { searchQuery = it },
                placeholder = { Text("Search by name, phone, car or plate...", color = Gray500, fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, null, tint = Gray500) },
                trailingIcon = { if (searchQuery.isNotBlank()) IconButton(onClick = { searchQuery = "" }) { Icon(Icons.Default.Clear, null, tint = Gray500) } },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                shape = RoundedCornerShape(12.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold)
            )
            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                listOf("" to "ALL", "available" to "AVAILABLE", "on_trip" to "ON TRIP", "inactive" to "INACTIVE").forEach { (v, l) ->
                    val sel = filter == v
                    Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { filter = v }, shape = RoundedCornerShape(8.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                        Text(l, color = if (sel) DarkBg else Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                    }
                }
            }
            if (loading) Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
            else if (filteredDrivers.isEmpty()) EmptyState(Icons.Default.DirectionsCar, "No drivers found")
            else LazyColumn(modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(bottom = 16.dp)) {
                items(filteredDrivers) { d ->
                    val statusColor = when (d.status) { "available" -> Emerald; "on_trip" -> Orange; else -> Gray500 }
                    Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable { onDriverClick(d.id) }, shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                        Column(modifier = Modifier.padding(14.dp)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(d.name, color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                    Text("${d.carModel} \u2022 ${d.plateNumber}", color = Gray400, fontSize = 12.sp)
                                }
                                Surface(modifier = Modifier.size(8.dp), shape = RoundedCornerShape(4.dp), color = if (d.isOnline == 1) Emerald else Gray600) {}
                                Spacer(Modifier.width(6.dp))
                                Surface(shape = RoundedCornerShape(6.dp), color = statusColor.copy(alpha = 0.15f)) {
                                    Text(d.status.uppercase(), color = statusColor, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                                }
                                Box {
                                    IconButton(onClick = { menuOpenFor = d }, modifier = Modifier.size(28.dp)) { Icon(Icons.Default.MoreVert, "More", tint = Gray400, modifier = Modifier.size(18.dp)) }
                                    DropdownMenu(expanded = menuOpenFor == d, onDismissRequest = { menuOpenFor = null }) {
                                        DropdownMenuItem(
                                            text = { Text("Edit", color = White) },
                                            leadingIcon = { Icon(Icons.Default.Edit, null, tint = Gold, modifier = Modifier.size(16.dp)) },
                                            onClick = {
                                                menuOpenFor = null
                                                eName = d.name; ePhone = d.phone; eCar = d.carModel; ePlate = d.plateNumber
                                                showEditDialog = d
                                            }
                                        )
                                        DropdownMenuItem(
                                            text = { Text("Toggle Status", color = White) },
                                            leadingIcon = { Icon(Icons.Default.ToggleOn, null, tint = Emerald, modifier = Modifier.size(16.dp)) },
                                            onClick = {
                                                menuOpenFor = null
                                                scope.launch { repo.toggleDriverStatus(d.id); refresh(); toast("Status toggled") }
                                            }
                                        )
                                        if (isAdmin) DropdownMenuItem(
                                            text = { Text("Delete", color = Red) },
                                            leadingIcon = { Icon(Icons.Default.Delete, null, tint = Red, modifier = Modifier.size(16.dp)) },
                                            onClick = { menuOpenFor = null; showDeleteDialog = d }
                                        )
                                    }
                                }
                            }
                            Spacer(Modifier.height(6.dp))
                            Row { Text("\u2605 ${String.format("%.1f", d.rating)}", color = Gold, fontSize = 12.sp); Spacer(Modifier.width(4.dp)); Text("(${d.totalRatings})", color = Gray500, fontSize = 11.sp); Spacer(Modifier.width(12.dp)); Text(d.phone, color = Gray300, fontSize = 12.sp) }
                            Spacer(Modifier.height(8.dp))
                            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                                SmallBtn("Call", Icons.Default.Phone) { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${d.phone}"))) }
                                SmallBtn("WhatsApp", Icons.Default.Chat) { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${d.phone.replace("+", "").replace(" ", "")}"))) }
                            }
                        }
                    }
                }
            }
        }
    }

    if (showAddDialog) AlertDialog(onDismissRequest = { showAddDialog = false }, containerColor = DarkBgLighter, title = { Text("Add Driver", color = White, fontWeight = FontWeight.Bold) }, text = {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) { PavanTextField(dName, { dName = it }, "Name"); PavanTextField(dPhone, { dPhone = it }, "Phone"); PavanTextField(dCar, { dCar = it }, "Car Model"); PavanTextField(dPlate, { dPlate = it }, "Plate Number") }
    }, confirmButton = { TextButton(onClick = { showAddDialog = false; scope.launch { val r = repo.addDriver(dName, dPhone, dCar, dPlate); toast(if (r.safeBool("success") == true) "Driver added!" else r.safeString("error") ?: "Failed"); dName = ""; dPhone = ""; dCar = ""; dPlate = ""; refresh() } }) { Text("Add", color = Gold) } }, dismissButton = { TextButton(onClick = { showAddDialog = false }) { Text("Cancel", color = Gray400) } })

    showEditDialog?.let { d ->
        AlertDialog(onDismissRequest = { showEditDialog = null }, containerColor = DarkBgLighter, title = { Text("Edit ${d.name}", color = Gold, fontWeight = FontWeight.Bold) }, text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                PavanTextField(eName, { eName = it }, "Name")
                PavanTextField(ePhone, { ePhone = it }, "Phone")
                PavanTextField(eCar, { eCar = it }, "Car Model")
                PavanTextField(ePlate, { ePlate = it }, "Plate Number")
            }
        }, confirmButton = {
            TextButton(onClick = {
                showEditDialog = null
                scope.launch {
                    val r = repo.editDriver(d.id, eName, ePhone, eCar, ePlate)
                    toast(if (r.safeBool("success") == true) "Driver updated!" else r.safeString("error") ?: "Failed")
                    refresh()
                }
            }) { Text("Save", color = Gold) }
        }, dismissButton = { TextButton(onClick = { showEditDialog = null }) { Text("Cancel", color = Gray400) } })
    }

    showDeleteDialog?.let { d ->
        AlertDialog(onDismissRequest = { showDeleteDialog = null }, containerColor = DarkBgLighter, title = { Text("Delete ${d.name}?", color = Red, fontWeight = FontWeight.Bold) }, text = { Text("This action cannot be undone.", color = Gray300) }, confirmButton = { TextButton(onClick = { showDeleteDialog = null; scope.launch { repo.deleteDriver(d.id); refresh(); toast("Driver deleted") } }) { Text("Delete", color = Red) } }, dismissButton = { TextButton(onClick = { showDeleteDialog = null }) { Text("Cancel", color = Gray400) } })
    }
}

@Composable
private fun SmallBtn(label: String, icon: androidx.compose.ui.graphics.vector.ImageVector, onClick: () -> Unit) {
    Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable(onClick = onClick), shape = RoundedCornerShape(8.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) {
        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = Gold, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(4.dp)); Text(label, color = White, fontSize = 10.sp, fontWeight = FontWeight.Medium)
        }
    }
}
