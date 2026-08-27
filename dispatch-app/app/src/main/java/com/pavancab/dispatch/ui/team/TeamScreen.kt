package com.pavancab.dispatch.ui.team

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
import com.pavancab.dispatch.model.TeamMember
import com.pavancab.dispatch.ui.components.*
import com.pavancab.dispatch.ui.theme.*
import com.pavancab.dispatch.utils.DateUtils
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
fun TeamScreen(onBack: () -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val repo = remember { Repository(context) }
    var members by remember { mutableStateOf<List<TeamMember>>(emptyList()) }
    var loading by remember { mutableStateOf(true) }
    var showAddDialog by remember { mutableStateOf(false) }
    var showDeleteDialog by remember { mutableStateOf<TeamMember?>(null) }
    var showRoleDialog by remember { mutableStateOf<TeamMember?>(null) }
    var menuOpenFor by remember { mutableStateOf<TeamMember?>(null) }
    var selectedRole by remember { mutableStateOf("team") }
    var mName by remember { mutableStateOf("") }
    var mPhone by remember { mutableStateOf("") }
    var mEmail by remember { mutableStateOf("") }
    var mRole by remember { mutableStateOf("team") }
    var isAdmin by remember { mutableStateOf(false) }

    suspend fun refresh() { members = repo.getTeamMembers(); loading = false }
    LaunchedEffect(Unit) { isAdmin = UserPrefs.isAdmin(context); refresh() }
    fun toast(msg: String) = Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Team Members", color = White, fontWeight = FontWeight.Black, fontSize = 18.sp) },
                navigationIcon = { IconButton(onClick = onBack) { Icon(Icons.Default.ArrowBack, "Back", tint = White) } },
                actions = {
                    if (isAdmin) {
                        IconButton(onClick = { showAddDialog = true }) { Icon(Icons.Default.PersonAdd, "Add", tint = Gold) }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = DarkBg)
            )
        },
        containerColor = DarkBg
    ) { padding ->
        if (loading) Box(modifier = Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) { CircularProgressIndicator(color = Gold) }
        else if (members.isEmpty()) EmptyState(Icons.Default.Group, "No team members", if (isAdmin) "Add team members to get started" else "No team members found")
        else LazyColumn(modifier = Modifier.fillMaxSize().padding(padding).padding(horizontal = 16.dp), verticalArrangement = Arrangement.spacedBy(8.dp), contentPadding = PaddingValues(vertical = 12.dp)) {
            items(members) { m ->
                Surface(modifier = Modifier.fillMaxWidth(), shape = RoundedCornerShape(14.dp), color = CardBg, border = BorderStroke(1.dp, CardBorder)) {
                    Column(modifier = Modifier.padding(14.dp)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(modifier = Modifier.weight(1f)) {
                                Text(m.memberName, color = White, fontSize = 15.sp, fontWeight = FontWeight.Bold)
                                if (m.memberPhone.isNotBlank()) Text(m.memberPhone, color = Gray400, fontSize = 12.sp)
                                if (m.memberEmail.isNotBlank()) Text(m.memberEmail, color = Gray500, fontSize = 11.sp)
                            }
                            Surface(shape = RoundedCornerShape(6.dp), color = if (m.role == "admin") Gold.copy(alpha = 0.15f) else Blue.copy(alpha = 0.15f)) {
                                Text(m.role.uppercase(), color = if (m.role == "admin") Gold else Blue, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                            }
                            Spacer(Modifier.width(8.dp))
                            Surface(shape = RoundedCornerShape(6.dp), color = if (m.isActive == 1) Emerald.copy(alpha = 0.15f) else Red.copy(alpha = 0.15f)) {
                                Text(if (m.isActive == 1) "ACTIVE" else "INACTIVE", color = if (m.isActive == 1) Emerald else Red, fontSize = 9.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp))
                            }
                            if (isAdmin) {
                                Box {
                                    IconButton(onClick = { menuOpenFor = m }, modifier = Modifier.size(28.dp)) { Icon(Icons.Default.MoreVert, "More", tint = Gray400, modifier = Modifier.size(18.dp)) }
                                    DropdownMenu(expanded = menuOpenFor == m, onDismissRequest = { menuOpenFor = null }) {
                                        DropdownMenuItem(
                                            text = { Text("Change Role", color = White) },
                                            leadingIcon = { Icon(Icons.Default.Security, null, tint = Gold, modifier = Modifier.size(16.dp)) },
                                            onClick = { menuOpenFor = null; selectedRole = m.role; showRoleDialog = m }
                                        )
                                    }
                                }
                            }
                        }
                        if (m.addedByEmail.isNotBlank() || m.invitedAt.isNotBlank()) {
                            Spacer(Modifier.height(2.dp))
                            val metaParts = mutableListOf<String>()
                            if (m.addedByEmail.isNotBlank()) metaParts.add("Added by ${m.addedByEmail}")
                            if (m.invitedAt.isNotBlank()) metaParts.add("Invited: ${DateUtils.formatDateTime(m.invitedAt)}")
                            Text(metaParts.joinToString("  \u2022  "), color = Gray500, fontSize = 10.sp)
                        }
                        Spacer(Modifier.height(8.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            if (m.memberPhone.isNotBlank()) {
                                Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { context.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:${m.memberPhone}"))) }, shape = RoundedCornerShape(8.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) { Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Default.Phone, null, tint = Gold, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(4.dp)); Text("Call", color = White, fontSize = 10.sp, fontWeight = FontWeight.Medium) } }
                                Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/${m.memberPhone.replace("+", "").replace(" ", "")}"))) }, shape = RoundedCornerShape(8.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) { Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Default.Chat, null, tint = Gold, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(4.dp)); Text("WhatsApp", color = White, fontSize = 10.sp, fontWeight = FontWeight.Medium) } }
                            }
                            Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { scope.launch { val r = repo.toggleTeamMember(m.id); toast(if (r.safeBool("success") == true) "Member is now ${if (m.isActive == 1) "INACTIVE" else "ACTIVE"}" else r.safeString("error") ?: "Failed"); refresh() } }, shape = RoundedCornerShape(8.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) { Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) { Icon(if (m.isActive == 1) Icons.Default.ToggleOn else Icons.Default.ToggleOff, null, tint = if (m.isActive == 1) Emerald else Gray400, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(4.dp)); Text(if (m.isActive == 1) "Set Inactive" else "Set Active", color = White, fontSize = 10.sp, fontWeight = FontWeight.Medium) } }
                            if (isAdmin) {
                                Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { showDeleteDialog = m }, shape = RoundedCornerShape(8.dp), color = DarkBgLighter, border = BorderStroke(1.dp, CardBorder)) { Row(modifier = Modifier.padding(horizontal = 8.dp, vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Default.Delete, null, tint = Red, modifier = Modifier.size(14.dp)); Spacer(Modifier.width(4.dp)); Text("Remove", color = Red, fontSize = 10.sp, fontWeight = FontWeight.Medium) } }
                            }
                        }
                    }
                }
            }
        }
    }
    if (isAdmin) {
        if (showAddDialog) AlertDialog(onDismissRequest = { showAddDialog = false }, containerColor = DarkBgLighter, title = { Text("Add Team Member", color = White, fontWeight = FontWeight.Bold) }, text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) { PavanTextField(mName, { mName = it }, "Name"); PavanTextField(mPhone, { mPhone = it }, "Phone"); PavanTextField(mEmail, { mEmail = it }, "Email"); Text("Role", color = Gray400, fontSize = 12.sp); Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) { listOf("team", "admin").forEach { r -> val sel = mRole == r; Surface(modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { mRole = r }, shape = RoundedCornerShape(8.dp), color = if (sel) Gold else CardBg, border = BorderStroke(1.dp, if (sel) Gold else CardBorder)) { Text(r.uppercase(), color = if (sel) DarkBg else Gray400, fontSize = 11.sp, fontWeight = FontWeight.Black, modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) } } } }
        }, confirmButton = { TextButton(onClick = { showAddDialog = false; scope.launch { val r = repo.addTeamMember(mName, mPhone, mEmail, mRole); toast(if (r.safeBool("success") == true) "Member added!" else r.safeString("error") ?: "Failed"); mName = ""; mPhone = ""; mEmail = ""; mRole = "team"; refresh() } }) { Text("Add", color = Gold) } }, dismissButton = { TextButton(onClick = { showAddDialog = false }) { Text("Cancel", color = Gray400) } })
        showDeleteDialog?.let { m ->
            AlertDialog(onDismissRequest = { showDeleteDialog = null }, containerColor = DarkBgLighter, title = { Text("Remove ${m.memberName}?", color = Red, fontWeight = FontWeight.Bold) }, text = { Text("They will lose access to the dispatch app.", color = Gray300) }, confirmButton = { TextButton(onClick = { showDeleteDialog = null; scope.launch { repo.removeTeamMember(m.id); refresh(); toast("Member removed") } }) { Text("Remove", color = Red) } }, dismissButton = { TextButton(onClick = { showDeleteDialog = null }) { Text("Cancel", color = Gray400) } })
        }
        showRoleDialog?.let { m ->
            AlertDialog(
                onDismissRequest = { showRoleDialog = null },
                containerColor = DarkBgLighter,
                title = { Text("Change Role \u2014 ${m.memberName}", color = White, fontWeight = FontWeight.Bold) },
                text = {
                    Column {
                        Text("Select the access level for this member.", color = Gray400, fontSize = 12.sp)
                        Spacer(Modifier.height(8.dp))
                        listOf("team" to "Team Member", "admin" to "Admin").forEach { (value, label) ->
                            Row(
                                modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(10.dp)).clickable { selectedRole = value }.padding(horizontal = 6.dp, vertical = 4.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                RadioButton(selected = selectedRole == value, onClick = { selectedRole = value }, colors = RadioButtonDefaults.colors(selectedColor = Gold, unselectedColor = Gray500))
                                Spacer(Modifier.width(8.dp))
                                Text(label, color = if (selectedRole == value) Gold else White, fontSize = 14.sp, fontWeight = if (selectedRole == value) FontWeight.Bold else FontWeight.Medium)
                            }
                        }
                    }
                },
                confirmButton = {
                    TextButton(onClick = {
                        val target = showRoleDialog
                        showRoleDialog = null
                        if (target != null) {
                            scope.launch {
                                val r = repo.updateTeamRole(target.id, selectedRole)
                                toast(if (r.safeBool("success") == true) "Role updated to ${selectedRole.uppercase()}!" else r.safeString("error") ?: "Failed")
                                refresh()
                            }
                        }
                    }) { Text("Save", color = Gold) }
                },
                dismissButton = { TextButton(onClick = { showRoleDialog = null }) { Text("Cancel", color = Gray400) } }
            )
        }
    }
}
