package com.pavancab.dispatch.ui.bookings

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.*
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.model.Booking
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import com.pavancab.dispatch.utils.DateUtils
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BookingsScreen(onBookingClick: (Int) -> Unit, onBack: () -> Unit) {
    val context = androidx.compose.ui.platform.LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var bookings by remember { mutableStateOf<List<Booking>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var loadingMore by remember { mutableStateOf(false) }
    var loadError by remember { mutableStateOf("") }
    var sessionExpired by remember { mutableStateOf(false) }
    var currentPage by remember { mutableIntStateOf(1) }
    var totalPages by remember { mutableIntStateOf(1) }
    var totalCount by remember { mutableIntStateOf(0) }
    var selectedFilter by remember { mutableStateOf("") }
    var frozenOnly by remember { mutableStateOf(false) }
    var searchQuery by remember { mutableStateOf("") }
    var startDate by remember { mutableStateOf("") }
    var endDate by remember { mutableStateOf("") }
    var showDatePicker by remember { mutableStateOf(false) }
    var pickingStart by remember { mutableStateOf(true) }
    val listState = rememberLazyListState()
    val itemsPerPage = 50

    suspend fun refresh() {
        loading = true
        loadError = ""
        sessionExpired = false
        currentPage = 1
        val result = repo.getAllBookingsPaged(selectedFilter, searchQuery, startDate, endDate, 1, itemsPerPage, if (frozenOnly) "1" else "")
        bookings = result.bookings
        totalPages = result.pages
        totalCount = result.total
        loading = false
        if (Repository.lastError == "SESSION_EXPIRED") {
            sessionExpired = true
            loadError = "Session expired. Please login again."
        } else if (Repository.lastError != null && bookings.isEmpty()) {
            loadError = Repository.lastError ?: "Failed to load bookings"
        }
        com.pavancab.dispatch.worker.AlarmScheduler.scheduleForRides(context, bookings)
    }

    suspend fun loadMore() {
        if (loadingMore || currentPage >= totalPages) return
        loadingMore = true
        val nextPage = currentPage + 1
        val result = repo.getAllBookingsPaged(selectedFilter, searchQuery, startDate, endDate, nextPage, itemsPerPage, if (frozenOnly) "1" else "")
        bookings = bookings + result.bookings
        currentPage = nextPage
        totalPages = result.pages
        totalCount = result.total
        loadingMore = false
    }

    LaunchedEffect(selectedFilter) { refresh() }
    LaunchedEffect(frozenOnly) { refresh() }
    LaunchedEffect(searchQuery) { delay(400); refresh() }
    LaunchedEffect(startDate, endDate) { refresh() }

    // Live auto-refresh — keep ride statuses and the Frozen category in sync without manual refresh
    LaunchedEffect(Unit) {
        while (true) { delay(10000); refresh() }
    }

    LaunchedEffect(listState) {
        snapshotFlow {
            val lastVisible = listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: 0
            val totalItems = listState.layoutInfo.totalItemsCount
            lastVisible >= totalItems - 3
        }.collect { shouldLoad ->
            if (shouldLoad && !loading && !loadingMore && currentPage < totalPages) {
                loadMore()
            }
        }
    }

    if (showDatePicker) {
        val datePickerState = rememberDatePickerState()
        DatePickerDialog(
            onDismissRequest = { showDatePicker = false },
            confirmButton = {
                TextButton(onClick = {
                    datePickerState.selectedDateMillis?.let { millis ->
                        val cal = java.util.Calendar.getInstance().apply { timeInMillis = millis }
                        val y = cal.get(java.util.Calendar.YEAR)
                        val m = cal.get(java.util.Calendar.MONTH) + 1
                        val d = cal.get(java.util.Calendar.DAY_OF_MONTH)
                        val formatted = String.format("%04d-%02d-%02d", y, m, d)
                        if (pickingStart) startDate = formatted else endDate = formatted
                    }
                    showDatePicker = false
                }) { Text("OK", color = Gold) }
            },
            dismissButton = {
                TextButton(onClick = { showDatePicker = false }) { Text("Cancel", color = Gray400) }
            }
        ) {
            DatePicker(state = datePickerState, colors = DatePickerDefaults.colors(containerColor = DarkBgLighter, selectedDayContainerColor = Gold, selectedDayContentColor = DarkBg))
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("All Bookings", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = {
                    if (startDate.isNotBlank() || endDate.isNotBlank()) {
                        IconButton(onClick = { startDate = ""; endDate = ""; scope.launch { refresh() } }) {
                            Icon(Icons.Default.FilterListOff, "Clear filters", tint = Orange)
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        Column(modifier = Modifier.fillMaxSize().padding(padding)) {
            OutlinedTextField(
                value = searchQuery, onValueChange = { searchQuery = it },
                placeholder = { Text("Search by name or phone...", color = Gray500, fontSize = 13.sp) },
                leadingIcon = { Icon(Icons.Default.Search, null, tint = Gray500) },
                modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
                shape = RoundedCornerShape(12.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = Gold, unfocusedBorderColor = Gray700, focusedContainerColor = CardBg, unfocusedContainerColor = CardBg, cursorColor = Gold)
            )

            Row(modifier = Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                listOf("" to "ALL", "PENDING" to "PENDING", "CONFIRMED" to "CONFIRMED", "IN_TRANSIT" to "ACTIVE", "COMPLETED" to "DONE", "CANCELLED" to "CANCELLED").forEach { (value, label) ->
                    val sel = selectedFilter == value && !frozenOnly
                    Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { frozenOnly = false; selectedFilter = value }, shape = RoundedCornerShape(8.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) {
                        Text(label, color = if (sel) DarkBg else Gray400, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                    }
                }
                val selFrozen = frozenOnly
                Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { selectedFilter = ""; frozenOnly = !frozenOnly }, shape = RoundedCornerShape(8.dp), color = if (selFrozen) Purple else CardBg, border = BorderStroke(1.dp, if (selFrozen) Purple else CardBorder)) {
                    Text("FREEZED RIDES", color = if (selFrozen) White else Purple, fontSize = 10.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 10.dp, vertical = 6.dp))
                }
            }

            Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { pickingStart = true; showDatePicker = true }, shape = RoundedCornerShape(10.dp), color = CardBg, border = BorderStroke(1.dp, if (startDate.isNotBlank()) Gold else CardBorder)) {
                    Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.CalendarToday, null, tint = if (startDate.isNotBlank()) Gold else Gray500, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(if (startDate.isNotBlank()) startDate else "Start Date", color = if (startDate.isNotBlank()) White else Gray500, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                    }
                }
                Surface(modifier = Modifier.weight(1f).clip(RoundedCornerShape(10.dp)).clickable { pickingStart = false; showDatePicker = true }, shape = RoundedCornerShape(10.dp), color = CardBg, border = BorderStroke(1.dp, if (endDate.isNotBlank()) Gold else CardBorder)) {
                    Row(modifier = Modifier.padding(horizontal = 12.dp, vertical = 10.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.CalendarToday, null, tint = if (endDate.isNotBlank()) Gold else Gray500, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(8.dp))
                        Text(if (endDate.isNotBlank()) endDate else "End Date", color = if (endDate.isNotBlank()) White else Gray500, fontSize = 12.sp, fontWeight = FontWeight.Medium)
                    }
                }
            }

            Text("   $totalCount bookings found  \u2022  Page $currentPage/$totalPages", color = Gray500, fontSize = 11.sp, modifier = Modifier.padding(horizontal = 16.dp, vertical = 2.dp))

            Spacer(Modifier.height(4.dp))
            if (loading && bookings.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
            } else if (sessionExpired) {
                Box(modifier = Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.Warning, null, tint = Red, modifier = Modifier.size(48.dp))
                        Spacer(Modifier.height(12.dp))
                        Text("Session Expired", color = White, fontSize = 18.sp, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(6.dp))
                        Text("Please logout and login again", color = Gray400, fontSize = 13.sp)
                    }
                }
            } else if (loadError.isNotBlank() && bookings.isEmpty()) {
                Box(modifier = Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(Icons.Default.ErrorOutline, null, tint = Red, modifier = Modifier.size(40.dp))
                        Spacer(Modifier.height(12.dp))
                        Text(loadError, color = Red, fontSize = 13.sp, textAlign = TextAlign.Center)
                        Spacer(Modifier.height(12.dp))
                        Surface(shape = RoundedCornerShape(8.dp), color = Gold, modifier = Modifier.clickable { scope.launch { refresh() } }) {
                            Text("Retry", color = DarkBg, fontSize = 13.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(horizontal = 24.dp, vertical = 10.dp))
                        }
                    }
                }
            } else if (bookings.isEmpty()) {
                EmptyState(Icons.Default.Inbox, "No bookings found")
            } else {
                LazyColumn(state = listState, modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(bottom = 16.dp)) {
                    items(bookings, key = { it.id }) { bk ->
                        Surface(modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).clickable { onBookingClick(bk.id) }, shape = RoundedCornerShape(12.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                            Column(modifier = Modifier.padding(12.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(bk.bookingRef, color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                                    if (bk.isFrozen == 1) {
                                        Surface(shape = RoundedCornerShape(999.dp), color = Purple.copy(alpha = 0.2f), border = BorderStroke(1.dp, Purple.copy(alpha = 0.6f))) {
                                            Text("FROZEN", color = Purple, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp))
                                        }
                                        Spacer(Modifier.width(6.dp))
                                    }
                                    StatusBadge(bk.status)
                                }
                                Spacer(Modifier.height(4.dp))
                                Text("${bk.customerName} \u2022 ${bk.customerPhone}", color = White, fontSize = 13.sp)
                                Text("${bk.pickupLocation} \u2192 ${bk.dropLocation}", color = Gray400, fontSize = 12.sp)
                                Spacer(Modifier.height(4.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.CalendarToday, null, tint = Gray500, modifier = Modifier.size(12.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text(DateUtils.formatDate(bk.pickupDate), color = Gray300, fontSize = 11.sp)
                                    Spacer(Modifier.width(10.dp))
                                    Icon(Icons.Default.Schedule, null, tint = Gray500, modifier = Modifier.size(12.dp))
                                    Spacer(Modifier.width(4.dp))
                                    Text(DateUtils.formatTime(bk.pickupTime), color = Gray300, fontSize = 11.sp)
                                    if (bk.createdAt.isNotBlank()) {
                                        Spacer(Modifier.width(10.dp))
                                        Text("Booked: ${bk.createdAt.substring(0, 16)}", color = Gray500, fontSize = 10.sp)
                                    }
                                }
                                Spacer(Modifier.height(4.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(displayCabType(bk.cabType), color = Gray300, fontSize = 12.sp, modifier = Modifier.weight(1f))
                                    Column(horizontalAlignment = Alignment.End) {
                                        if (bk.userOfferedFare > 0) {
                                            // User offered their own price (own-price booking or boost)
                                            Text("User offered: \u20B9${bk.userOfferedFare.toInt()}", color = BlueAccent, fontSize = 10.sp, fontWeight = FontWeight.Bold)
                                            if (bk.baseFare > 0 && bk.baseFare != bk.userOfferedFare) {
                                                Text("Route price: \u20B9${bk.baseFare.toInt()}", color = Gray500, fontSize = 9.sp)
                                            }
                                        } else if (bk.baseFare > 0 && bk.baseFare != bk.totalFare) {
                                            // Base differs from total (e.g. night surcharge or boost applied)
                                            Text("Route price: \u20B9${bk.baseFare.toInt()}", color = Gray400, fontSize = 10.sp)
                                        }
                                        Text(fmt(bk.totalFare), color = Gold, fontSize = 14.sp, fontWeight = FontWeight.Bold)
                                    }
                                }
                                if (bk.driverName.isNotBlank()) Text("Driver: ${bk.driverName}", color = Emerald, fontSize = 11.sp, modifier = Modifier.padding(top = 2.dp))
                                if (bk.bookingSource.isNotBlank() && bk.bookingSource != "app") {
                                    Text("\uD83D\uDCDE Phone Booking", color = Orange, fontSize = 10.sp, fontWeight = FontWeight.Bold, modifier = Modifier.padding(top = 2.dp))
                                }
                            }
                        }
                    }
                    if (loadingMore) {
                        item { Box(modifier = Modifier.fillMaxWidth().padding(16.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold, modifier = Modifier.size(24.dp)) } }
                    }
                    if (!loadingMore && currentPage >= totalPages && bookings.isNotEmpty()) {
                        item { Text("End of list", color = Gray600, fontSize = 11.sp, modifier = Modifier.fillMaxWidth().padding(16.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center) }
                    }
                }
            }
        }
    }
}
