package com.pavancab.dispatch.ui.theme

import android.app.Activity
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import android.os.Build

private val DarkColorScheme = darkColorScheme(
    primary = Gold,
    onPrimary = DarkBg,
    secondary = Emerald,
    tertiary = Blue,
    background = DarkBg,
    surface = CardBg,
    error = Red,
    onBackground = White,
    onSurface = White,
    outline = Gray700,
    surfaceVariant = DarkBgLighter,
    onSurfaceVariant = Gray400
)

@Composable
fun DispatchTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = DarkColorScheme,
        typography = Typography(),
        content = content
    )
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = DarkBg.toArgb()
            window.navigationBarColor = DarkBg.toArgb()
        }
    }
}
