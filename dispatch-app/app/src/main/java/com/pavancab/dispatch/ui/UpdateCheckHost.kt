package com.pavancab.dispatch.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import com.pavancab.dispatch.data.UpdateInfo
import com.pavancab.dispatch.data.UpdateManager

@Composable
fun UpdateCheckHost(
    content: @Composable () -> Unit
) {
    val context = LocalContext.current
    var updateInfo by remember { mutableStateOf<UpdateInfo?>(null) }

    LaunchedEffect(Unit) {
        try {
            updateInfo = UpdateManager.check(context)
        } catch (_: Exception) {}
    }

    Box {
        content()
        updateInfo?.let { info ->
            UpdateDialog(
                info = info,
                onRemindLater = {
                    UpdateManager.rememberDismissed(context, info.latestVersionCode)
                    updateInfo = null
                },
                onDismiss = { updateInfo = null }
            )
        }
    }
}
