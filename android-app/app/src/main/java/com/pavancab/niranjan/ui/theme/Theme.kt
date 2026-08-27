package com.pavancab.niranjan.ui.theme

import android.app.Activity
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val PavanCabColorScheme = darkColorScheme(
    primary = Gold,
    onPrimary = DarkBg,
    primaryContainer = GoldDark,
    onPrimaryContainer = White,
    secondary = Emerald,
    onSecondary = DarkBg,
    secondaryContainer = EmeraldDark,
    onSecondaryContainer = White,
    tertiary = Blue,
    onTertiary = White,
    background = DarkBg,
    onBackground = White,
    surface = CardBg,
    onSurface = White,
    surfaceVariant = CardBgLight,
    onSurfaceVariant = Gray400,
    error = Red,
    onError = White,
    outline = Gray700,
    outlineVariant = DividerColor
)

@Composable
fun PavanCabTheme(content: @Composable () -> Unit) {
    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = DarkBg.toArgb()
            window.navigationBarColor = Color.Black.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = false
        }
    }
    MaterialTheme(
        colorScheme = PavanCabColorScheme,
        content = content
    )
}
