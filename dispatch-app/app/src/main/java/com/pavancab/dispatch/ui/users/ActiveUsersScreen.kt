package com.pavancab.dispatch.ui.users

import android.app.DatePickerDialog
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.*
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.model.ActiveUser
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import com.pavancab.dispatch.utils.DateUtils
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

private enum class UserFilter(val label: String) {
    ALL("All"), ONLINE("Online"), TODAY("Today"), YESTERDAY("Yesterday"),
    HAS_RIDES("With Rides"), NO_RIDES("No Rides"), BANNED("Banned")
}

private data class PushTemplate(val label: String, val title: String, val body: String)

private val pushTemplates = listOf(
    PushTemplate("\u20B9200 OFF", "Special Offer!", "Get \u20B9200 OFF on your next ride with Pavan Cab!"),
    PushTemplate("Cab Reminder", "Your Ride Awaits!", "Your Pavan Cab ride is scheduled. Open the app for details."),
    PushTemplate("VIP Priority", "VIP Status", "You've been upgraded to VIP priority! Enjoy faster pickups."),
    PushTemplate("Airport Pickup", "Airport Transfer", "Need an airport pickup? Book now on Pavan Cab!")
)

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
fun ActiveUsersScreen(onBack: () -> Unit, onUserClick: (String, String, Int) -> Unit = { _, _, _ -> }) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var users by remember { mutableStateOf<List<ActiveUser>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var selectedFilter by remember { mutableStateOf(UserFilter.ALL) }
    var selectedUsers by remember { mutableStateOf(setOf<Int>()) }
    var role by remember { mutableStateOf("") }

    var startDate by remember { mutableStateOf("") }
    var endDate by remember { mutableStateOf("") }
    var showDateFilter by remember { mutableStateOf(false) }

    var showPushDialog by remember { mutableStateOf(false) }
    var pushTarget by remember { mutableStateOf<List<ActiveUser>>(emptyList()) }
    var pushTitle by remember { mutableStateOf("") }
    var pushBody by remember { mutableStateOf("") }

    var showWhatsAppDialog by remember { mutableStateOf(false) }
    var waTarget by remember { mutableStateOf<List<ActiveUser>>(emptyList()) }
    var waMessage by remember { mutableStateOf("") }

    var showIndividualPush by remember { mutableStateOf(false) }
    var individualUser by remember { mutableStateOf<ActiveUser?>(null) }
    var indPushTitle by remember { mutableStateOf("") }
    var indPushBody by remember { mutableStateOf("") }

    var showBanDialog by remember { mutableStateOf(false) }
    var banTarget by remember { mutableStateOf<ActiveUser?>(null) }
    var showDeleteUserDialog by remember { mutableStateOf(false) }
    var deleteUserTarget by remember { mutableStateOf<ActiveUser?>(null) }

    fun loadUsers() {
        loading = true
        scope.launch {
            users = repo.getActiveUsers(startDate, endDate)
            loading = false
        }
    }

    fun parseDate(dateStr: String): Date? {
        return try {
            val formats = listOf(
                SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()),
                SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.getDefault()),
                SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.getDefault())
            )
            for (fmt in formats) { try { return fmt.parse(dateStr) } catch (_: Exception) {} }
            null
        } catch (_: Exception) { null }
    }

    fun isToday(dateStr: String): Boolean {
        val d = parseDate(dateStr) ?: return false
        val cal = Calendar.getInstance()
        val todayCal = Calendar.getInstance()
        cal.time = d
        return cal.get(Calendar.YEAR) == todayCal.get(Calendar.YEAR) &&
               cal.get(Calendar.DAY_OF_YEAR) == todayCal.get(Calendar.DAY_OF_YEAR)
    }

    fun isYesterday(dateStr: String): Boolean {
        val d = parseDate(dateStr) ?: return false
        val cal = Calendar.getInstance()
        val yesterdayCal = Calendar.getInstance()
        cal.time = d
        yesterdayCal.add(Calendar.DAY_OF_YEAR, -1)
        return cal.get(Calendar.YEAR) == yesterdayCal.get(Calendar.YEAR) &&
               cal.get(Calendar.DAY_OF_YEAR) == yesterdayCal.get(Calendar.DAY_OF_YEAR)
    }

    val filteredUsers = remember(users, selectedFilter) {
        when (selectedFilter) {
            UserFilter.ALL -> users
            UserFilter.ONLINE -> users.filter { it.liveAppStatus == "ONLINE_OPEN" }
            UserFilter.TODAY -> users.filter { it.lastActiveAt.isNotBlank() && isToday(it.lastActiveAt) }
            UserFilter.YESTERDAY -> users.filter { it.lastActiveAt.isNotBlank() && isYesterday(it.lastActiveAt) }
            UserFilter.HAS_RIDES -> users.filter { it.totalBookings > 0 }
            UserFilter.NO_RIDES -> users.filter { it.totalBookings == 0 }
            UserFilter.BANNED -> users.filter { it.isBanned == 1 }
        }
    }

    val onlineCount = users.count { it.liveAppStatus == "ONLINE_OPEN" }
    val todayCount = users.count { it.lastActiveAt.isNotBlank() && isToday(it.lastActiveAt) }
    val ridesCount = users.count { it.totalBookings > 0 }
    val bannedCount = users.count { it.isBanned == 1 }

    LaunchedEffect(Unit) {
        role = UserPrefs.getRole(context)
        loadUsers()
    }

    // Auto-refresh live user status while the screen is visible (silent, no overlay flash)
    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    LaunchedEffect(Unit) {
        while (true) {
            kotlinx.coroutines.delay(15000)
            if (lifecycleOwner.lifecycle.currentState.isAtLeast(androidx.lifecycle.Lifecycle.State.STARTED)) {
                scope.launch {
                    val updated = kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) { repo.getActiveUsers(startDate, endDate) }
                    users = updated
                }
            }
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Active Users", color = White, fontWeight = FontWeight.Black, fontSize = 16.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = {
                    IconButton(onClick = { loadUsers() }) { Icon(Icons.Default.Refresh, null, tint = White) }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {

            // Summary row
            androidx.compose.foundation.lazy.LazyRow(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 6.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                item { SummaryPill("Total", "${users.size}", White) }
                item { SummaryPill("Online", "$onlineCount", Emerald) }
                item { SummaryPill("Today", "$todayCount", Gold) }
                item { SummaryPill("Rides", "$ridesCount", Blue) }
                if (bannedCount > 0) item { SummaryPill("Banned", "$bannedCount", Red) }
            }

            // Date range filter bar
            Row(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                OutlinedButton(
                    onClick = { showDateFilter = true },
                    modifier = Modifier.weight(1f),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = if (startDate.isNotBlank()) Gold else Gray400),
                    border = BorderStroke(1.dp, if (startDate.isNotBlank()) Gold else CardBorder)
                ) {
                    Icon(Icons.Default.CalendarMonth, null, modifier = Modifier.size(14.dp))
                    Spacer(Modifier.width(4.dp))
                    Text(
                        if (startDate.isNotBlank()) "$startDate → $endDate" else "Date Range",
                        fontSize = 10.sp, fontWeight = FontWeight.Bold
                    )
                }
                if (startDate.isNotBlank()) {
                    IconButton(onClick = { startDate = ""; endDate = ""; loadUsers() }, modifier = Modifier.size(32.dp)) {
                        Icon(Icons.Default.Clear, "Clear", tint = Gray400, modifier = Modifier.size(16.dp))
                    }
                }
            }

            // Category filter chips
            androidx.compose.foundation.lazy.LazyRow(
                modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 4.dp),
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                items(UserFilter.entries.size) { index ->
                    val filter = UserFilter.entries[index]
                    val count = when (filter) {
                        UserFilter.ALL -> users.size
                        UserFilter.ONLINE -> onlineCount
                        UserFilter.TODAY -> todayCount
                        UserFilter.YESTERDAY -> users.count { it.lastActiveAt.isNotBlank() && isYesterday(it.lastActiveAt) }
                        UserFilter.HAS_RIDES -> ridesCount
                        UserFilter.NO_RIDES -> users.count { it.totalBookings == 0 }
                        UserFilter.BANNED -> bannedCount
                    }
                    val selected = selectedFilter == filter
                    Surface(
                        modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedFilter = filter; selectedUsers = emptySet() },
                        shape = RoundedCornerShape(8.dp),
                        color = if (selected) Gold else DarkBgLighter,
                        border = BorderStroke(1.dp, if (selected) Gold else CardBorder)
                    ) {
                        Text(
                            "${filter.label} ($count)",
                            color = if (selected) DarkBg else Gray400,
                            fontSize = 9.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp)
                        )
                    }
                }
            }

            // Selection bar
            if (filteredUsers.isNotEmpty()) {
                Row(
                    modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    TextButton(onClick = {
                        if (selectedUsers.size == filteredUsers.size) {
                            selectedUsers = emptySet()
                        } else {
                            selectedUsers = filteredUsers.map { it.userId }.toSet()
                        }
                    }) {
                        Text(
                            if (selectedUsers.size == filteredUsers.size) "Deselect All" else "Select All (${filteredUsers.size})",
                            color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold
                        )
                    }
                    if (selectedUsers.isNotEmpty()) {
                        Text("${selectedUsers.size} selected", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }

            // Content area
            Box(modifier = Modifier.weight(1f).fillMaxWidth()) {
                when {
                    loading -> {
                        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator(color = Gold)
                        }
                    }
                    filteredUsers.isEmpty() -> {
                        Box(modifier = Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(Icons.Default.People, contentDescription = null, tint = Gray600, modifier = Modifier.size(56.dp))
                                Spacer(Modifier.height(12.dp))
                                Text("No Users", color = Gray400, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
                                Spacer(Modifier.height(4.dp))
                                Text("No users match this filter", color = Gray500, fontSize = 12.sp)
                            }
                        }
                    }
                    else -> {
                        LazyColumn(
                            modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                            contentPadding = PaddingValues(vertical = 8.dp)
                        ) {
                            items(filteredUsers, key = { "${it.userId}_${it.mobile}" }) { user ->
                                UserCard(
                                    user = user,
                                    isSelected = selectedUsers.contains(user.userId),
                                    onClick = { onUserClick(user.mobile, user.email, user.userId) },
                                    onToggleSelect = {
                                        selectedUsers = if (selectedUsers.contains(user.userId)) {
                                            selectedUsers - user.userId
                                        } else {
                                            selectedUsers + user.userId
                                        }
                                    },
                                    onSendPush = {
                                        individualUser = user; indPushTitle = ""; indPushBody = ""
                                        showIndividualPush = true
                                    },
                                    onBan = { banTarget = user; showBanDialog = true },
                                    onDelete = { deleteUserTarget = user; showDeleteUserDialog = true },
                                    isAdmin = role == "admin"
                                )
                            }
                        }
                    }
                }
            }

            // Bottom action bar when users are selected
            if (selectedUsers.isNotEmpty()) {
                Surface(
                    modifier = Modifier.fillMaxWidth(),
                    color = DarkBgLighter,
                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Button(
                            onClick = {
                                val targets = filteredUsers.filter { selectedUsers.contains(it.userId) }
                                pushTarget = targets; pushTitle = ""; pushBody = ""
                                showPushDialog = true
                            },
                            modifier = Modifier.weight(1f).height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Gold)
                        ) {
                            Icon(Icons.Default.Notifications, null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("FCM (${selectedUsers.size})", fontWeight = FontWeight.Black, fontSize = 11.sp)
                        }
                        Button(
                            onClick = {
                                val targets = filteredUsers.filter { selectedUsers.contains(it.userId) }
                                    .filter { it.mobile.isNotBlank() }
                                if (targets.isEmpty()) {
                                    Toast.makeText(context, "No users with phone numbers", Toast.LENGTH_SHORT).show()
                                    return@Button
                                }
                                waTarget = targets; waMessage = ""
                                showWhatsAppDialog = true
                            },
                            modifier = Modifier.weight(1f).height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF25D366))
                        ) {
                            Icon(Icons.Default.Chat, null, modifier = Modifier.size(16.dp))
                            Spacer(Modifier.width(4.dp))
                            Text("WhatsApp", fontWeight = FontWeight.Black, fontSize = 11.sp)
                        }
                    }
                }
            }
        }

        // Date range picker dialog
        if (showDateFilter) {
            val cal = Calendar.getInstance()
            AlertDialog(
                onDismissRequest = { showDateFilter = false },
                containerColor = DarkBgLighter,
                title = { Text("Select Date Range", color = Gold, fontWeight = FontWeight.Bold, fontSize = 16.sp) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("Filter users who had activity in a date range.", color = Gray400, fontSize = 12.sp)
                        Surface(
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable {
                                DatePickerDialog(context, { _, y, m, d ->
                                    startDate = String.format("%04d-%02d-%02d", y, m + 1, d)
                                }, cal.get(Calendar.YEAR), cal.get(Calendar.MONTH), cal.get(Calendar.DAY_OF_MONTH)).show()
                            },
                            shape = RoundedCornerShape(10.dp), color = CardBg,
                            border = BorderStroke(1.dp, if (startDate.isNotBlank()) Gold else CardBorder)
                        ) {
                            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.CalendarToday, null, tint = Gold, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Start: ${startDate.ifBlank { "Tap to select" }}", color = if (startDate.isNotBlank()) White else Gray400, fontSize = 13.sp)
                            }
                        }
                        Surface(
                            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable {
                                DatePickerDialog(context, { _, y, m, d ->
                                    endDate = String.format("%04d-%02d-%02d", y, m + 1, d)
                                }, cal.get(Calendar.YEAR), cal.get(Calendar.MONTH), cal.get(Calendar.DAY_OF_MONTH)).show()
                            },
                            shape = RoundedCornerShape(10.dp), color = CardBg,
                            border = BorderStroke(1.dp, if (endDate.isNotBlank()) Gold else CardBorder)
                        ) {
                            Row(modifier = Modifier.padding(14.dp), verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.CalendarMonth, null, tint = Gold, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("End: ${endDate.ifBlank { "Tap to select" }}", color = if (endDate.isNotBlank()) White else Gray400, fontSize = 13.sp)
                            }
                        }
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        showDateFilter = false
                        if (startDate.isNotBlank() || endDate.isNotBlank()) loadUsers()
                    }) { Text("Apply", color = Gold) }
                },
                dismissButton = {
                    TextButton(onClick = { showDateFilter = false; startDate = ""; endDate = ""; loadUsers() }) { Text("Clear & Close", color = Gray400) }
                }
            )
        }

        // Individual push dialog
        if (showIndividualPush && individualUser != null) {
            AlertDialog(
                onDismissRequest = { showIndividualPush = false },
                containerColor = DarkBgLighter,
                title = { Text("Send to ${individualUser!!.name.ifBlank { individualUser!!.mobile }}", color = Gold, fontWeight = FontWeight.Bold, fontSize = 14.sp) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Send a personal notification to this user's device.", color = Gray400, fontSize = 12.sp)
                        Text("Quick templates:", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        androidx.compose.foundation.lazy.LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            items(pushTemplates.size) { idx ->
                                val tpl = pushTemplates[idx]
                                Surface(
                                    modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { indPushTitle = tpl.title; indPushBody = tpl.body },
                                    shape = RoundedCornerShape(8.dp),
                                    color = Blue.copy(alpha = 0.10f),
                                    border = BorderStroke(1.dp, Blue.copy(alpha = 0.3f))
                                ) {
                                    Text(tpl.label, color = BlueAccent, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                                }
                            }
                        }
                        OutlinedTextField(
                            value = indPushTitle, onValueChange = { indPushTitle = it },
                            label = { Text("Title *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 56.dp),
                            shape = RoundedCornerShape(12.dp), singleLine = true,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                        OutlinedTextField(
                            value = indPushBody, onValueChange = { indPushBody = it },
                            label = { Text("Message *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 100.dp, max = 200.dp),
                            shape = RoundedCornerShape(12.dp), minLines = 4, maxLines = 8,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        if (indPushTitle.isBlank() || indPushBody.isBlank()) {
                            Toast.makeText(context, "Title and message required", Toast.LENGTH_SHORT).show()
                            return@TextButton
                        }
                        showIndividualPush = false
                        scope.launch {
                            val r = repo.sendPersonalPush(individualUser!!.mobile, individualUser!!.email, indPushTitle, indPushBody)
                            val msg = if (r.safeBool("success") == true) "Sent!" else r.safeString("error") ?: "Failed"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        }
                    }) { Text("Send", color = Gold) }
                },
                dismissButton = { TextButton(onClick = { showIndividualPush = false }) { Text("Cancel", color = Gray400) } }
            )
        }

        // Bulk push dialog
        if (showPushDialog) {
            AlertDialog(
                onDismissRequest = { showPushDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("Send Push to ${pushTarget.size} Users", color = Gold, fontWeight = FontWeight.Bold, fontSize = 14.sp) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Send a push notification to all selected users.", color = Gray400, fontSize = 12.sp)
                        Text("Quick templates:", color = Gold, fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        androidx.compose.foundation.lazy.LazyRow(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            items(pushTemplates.size) { idx ->
                                val tpl = pushTemplates[idx]
                                Surface(
                                    modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { pushTitle = tpl.title; pushBody = tpl.body },
                                    shape = RoundedCornerShape(8.dp),
                                    color = Blue.copy(alpha = 0.10f),
                                    border = BorderStroke(1.dp, Blue.copy(alpha = 0.3f))
                                ) {
                                    Text(tpl.label, color = BlueAccent, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                                }
                            }
                        }
                        OutlinedTextField(
                            value = pushTitle, onValueChange = { pushTitle = it },
                            label = { Text("Title *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 56.dp),
                            shape = RoundedCornerShape(12.dp), singleLine = true,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                        OutlinedTextField(
                            value = pushBody, onValueChange = { pushBody = it },
                            label = { Text("Message *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 100.dp, max = 200.dp),
                            shape = RoundedCornerShape(12.dp), minLines = 4, maxLines = 8,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold, focusedLabelColor = Gold, unfocusedLabelColor = Gray400)
                        )
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        if (pushTitle.isBlank() || pushBody.isBlank()) {
                            Toast.makeText(context, "Title and message required", Toast.LENGTH_SHORT).show()
                            return@TextButton
                        }
                        showPushDialog = false
                        scope.launch {
                            val phones = pushTarget.map { it.mobile }.filter { it.isNotBlank() }
                            val emails = pushTarget.map { it.email }.filter { it.isNotBlank() }
                            val tokens = repo.getBulkFcmTokens(phones, emails)
                            if (tokens.isEmpty()) {
                                Toast.makeText(context, "No FCM tokens found for selected users", Toast.LENGTH_SHORT).show()
                                return@launch
                            }
                            val r = repo.bulkPush(tokens, pushTitle, pushBody)
                            val msg = if (r.safeBool("success") == true) "Push sent to ${tokens.size} devices!" else r.safeString("error") ?: "Failed"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                            selectedUsers = emptySet()
                        }
                    }) { Text("Send", color = Gold) }
                },
                dismissButton = { TextButton(onClick = { showPushDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }

        // Bulk WhatsApp dialog
        if (showWhatsAppDialog) {
            AlertDialog(
                onDismissRequest = { showWhatsAppDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("WhatsApp to ${waTarget.size} Users", color = Color(0xFF25D366), fontWeight = FontWeight.Bold, fontSize = 14.sp) },
                text = {
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("Send a WhatsApp message to all selected users.", color = Gray400, fontSize = 12.sp)
                        Text("Auto-footer will be added automatically.", color = Gray500, fontSize = 11.sp)
                        OutlinedTextField(
                            value = waMessage, onValueChange = { waMessage = it },
                            label = { Text("Message *") },
                            modifier = Modifier.fillMaxWidth().heightIn(min = 120.dp, max = 250.dp),
                            shape = RoundedCornerShape(12.dp), minLines = 5, maxLines = 10,
                            colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Color(0xFF25D366), unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Color(0xFF25D366), focusedLabelColor = Color(0xFF25D366), unfocusedLabelColor = Gray400)
                        )
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        if (waMessage.isBlank()) {
                            Toast.makeText(context, "Message required", Toast.LENGTH_SHORT).show()
                            return@TextButton
                        }
                        showWhatsAppDialog = false
                        scope.launch {
                            val phones = waTarget.map { it.mobile }.filter { it.isNotBlank() }
                            val r = repo.bulkWhatsApp(phones, waMessage)
                            val msg = if (r.safeBool("success") == true) "WhatsApp sent to ${phones.size} users!" else r.safeString("error") ?: "Failed"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                            selectedUsers = emptySet()
                        }
                    }) { Text("Send", color = Color(0xFF25D366)) }
                },
                dismissButton = { TextButton(onClick = { showWhatsAppDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }

        // Ban user dialog
        if (showBanDialog && banTarget != null) {
            val isBanned = banTarget!!.isBanned == 1
            AlertDialog(
                onDismissRequest = { showBanDialog = false },
                containerColor = DarkBgLighter,
                title = { Text(if (isBanned) "Unban User?" else "Ban User?", color = if (isBanned) Emerald else Red, fontWeight = FontWeight.Bold, fontSize = 16.sp) },
                text = {
                    Text(
                        if (isBanned) "This will allow ${banTarget!!.name.ifBlank { banTarget!!.mobile }} to log in again."
                        else "This will prevent ${banTarget!!.name.ifBlank { banTarget!!.mobile }} from logging in. They will see a ban message.",
                        color = Gray300, fontSize = 13.sp
                    )
                },
                confirmButton = {
                    TextButton(onClick = {
                        showBanDialog = false
                        scope.launch {
                            val r = repo.banUser(banTarget!!.userId, !isBanned)
                            val msg = if (r.safeBool("success") == true) {
                                loadUsers()
                                if (isBanned) "User unbanned!" else "User banned!"
                            } else r.safeString("error") ?: "Failed"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        }
                    }) { Text(if (isBanned) "Unban" else "Ban", color = if (isBanned) Emerald else Red) }
                },
                dismissButton = { TextButton(onClick = { showBanDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }

        // Delete user dialog
        if (showDeleteUserDialog && deleteUserTarget != null) {
            AlertDialog(
                onDismissRequest = { showDeleteUserDialog = false },
                containerColor = DarkBgLighter,
                title = { Text("Delete User Permanently?", color = Red, fontWeight = FontWeight.Bold, fontSize = 16.sp) },
                text = {
                    Text(
                        "This will permanently delete ${deleteUserTarget!!.name.ifBlank { deleteUserTarget!!.mobile }} from the database. This cannot be undone!",
                        color = Gray300, fontSize = 13.sp
                    )
                },
                confirmButton = {
                    TextButton(onClick = {
                        showDeleteUserDialog = false
                        scope.launch {
                            val r = repo.deleteUser(deleteUserTarget!!.userId)
                            val msg = if (r.safeBool("success") == true) {
                                loadUsers()
                                "User deleted!"
                            } else r.safeString("error") ?: "Failed"
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        }
                    }) { Text("DELETE", color = Red) }
                },
                dismissButton = { TextButton(onClick = { showDeleteUserDialog = false }) { Text("Cancel", color = Gray400) } }
            )
        }
    }
}

@Composable
private fun SummaryPill(label: String, count: String, color: Color) {
    Surface(shape = RoundedCornerShape(8.dp), color = color.copy(alpha = 0.12f), border = BorderStroke(1.dp, color.copy(alpha = 0.3f))) {
        Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(count, color = color, fontSize = 12.sp, fontWeight = FontWeight.Black)
            Spacer(Modifier.width(3.dp))
            Text(label, color = color.copy(alpha = 0.7f), fontSize = 9.sp, fontWeight = FontWeight.Medium)
        }
    }
}

@Composable
private fun UserCard(
    user: ActiveUser,
    isSelected: Boolean,
    onClick: () -> Unit,
    onToggleSelect: () -> Unit,
    onSendPush: () -> Unit,
    onBan: () -> Unit,
    onDelete: () -> Unit,
    isAdmin: Boolean
) {
    val isOnline = user.liveAppStatus == "ONLINE_OPEN"
    val isBanned = user.isBanned == 1
    val statusColor = when {
        isBanned -> Red
        isOnline -> Emerald
        else -> Gray600
    }

    PavanCard(modifier = Modifier.clickable(onClick = onClick)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Checkbox(
                checked = isSelected,
                onCheckedChange = { onToggleSelect() },
                colors = CheckboxDefaults.colors(checkedColor = Gold, uncheckedColor = Gray500)
            )
            Surface(modifier = Modifier.size(8.dp), shape = RoundedCornerShape(4.dp), color = statusColor) {}
            Spacer(Modifier.width(6.dp))
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(user.name.ifBlank { "User" }, color = White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    if (isBanned) {
                        Spacer(Modifier.width(6.dp))
                        Surface(shape = RoundedCornerShape(4.dp), color = Red.copy(alpha = 0.15f)) {
                            Text("BANNED", color = Red, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 5.dp, vertical = 2.dp))
                        }
                    }
                }
                Text(user.mobile.ifBlank { user.email }, color = Gray400, fontSize = 11.sp)
            }
            if (isOnline) {
                Surface(shape = RoundedCornerShape(4.dp), color = Emerald.copy(alpha = 0.15f)) {
                    Text("ONLINE", color = Emerald, fontSize = 8.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                }
            }
        }
        if (user.lastActiveAt.isNotBlank()) {
            Spacer(Modifier.height(2.dp))
            Text("Last active: ${DateUtils.formatDateTime(user.lastActiveAt)}", color = Gray500, fontSize = 10.sp, modifier = Modifier.padding(start = 48.dp))
        }
        Spacer(Modifier.height(2.dp))
        Row(modifier = Modifier.padding(start = 48.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("${user.totalBookings} rides", color = Gray400, fontSize = 10.sp)
            if (user.completedBookings > 0) Text("${user.completedBookings} done", color = Emerald, fontSize = 10.sp)
            if (user.cancelledBookings > 0) Text("${user.cancelledBookings} cancelled", color = Red.copy(alpha = 0.7f), fontSize = 10.sp)
            if (user.totalSpent > 0) Text("\u20B9${String.format("%.0f", user.totalSpent)}", color = Gold, fontSize = 10.sp)
        }
        Spacer(Modifier.height(4.dp))
        Row(modifier = Modifier.padding(start = 48.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Surface(
                modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable { onSendPush() },
                shape = RoundedCornerShape(6.dp),
                color = Gold.copy(alpha = 0.08f),
                border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))
            ) {
                Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
                    Icon(Icons.Default.Notifications, null, tint = Gold, modifier = Modifier.size(12.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Push", color = Gold, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                }
            }
            if (isAdmin && user.userId > 0) {
                Surface(
                    modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable { onBan() },
                    shape = RoundedCornerShape(6.dp),
                    color = if (isBanned) Emerald.copy(alpha = 0.08f) else Orange.copy(alpha = 0.08f),
                    border = BorderStroke(1.dp, if (isBanned) Emerald.copy(alpha = 0.2f) else Orange.copy(alpha = 0.2f))
                ) {
                    Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(if (isBanned) Icons.Default.LockOpen else Icons.Default.Block, null, tint = if (isBanned) Emerald else Orange, modifier = Modifier.size(12.dp))
                        Spacer(Modifier.width(4.dp))
                        Text(if (isBanned) "Unban" else "Ban", color = if (isBanned) Emerald else Orange, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                }
                Surface(
                    modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable { onDelete() },
                    shape = RoundedCornerShape(6.dp),
                    color = Red.copy(alpha = 0.08f),
                    border = BorderStroke(1.dp, Red.copy(alpha = 0.2f))
                ) {
                    Row(modifier = Modifier.padding(horizontal = 10.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Delete, null, tint = Red, modifier = Modifier.size(12.dp))
                        Spacer(Modifier.width(4.dp))
                        Text("Delete", color = Red, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    }
}
