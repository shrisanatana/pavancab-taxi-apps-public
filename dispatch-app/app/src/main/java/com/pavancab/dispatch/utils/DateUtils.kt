package com.pavancab.dispatch.utils

import java.text.SimpleDateFormat
import java.util.*

object DateUtils {
    private val displayDateFormat = SimpleDateFormat("dd-MM-yyyy", Locale.getDefault())
    private val displayTimeFormat = SimpleDateFormat("hh:mm a", Locale.getDefault())
    private val displayDateTimeFormat = SimpleDateFormat("dd-MM-yyyy, hh:mm a", Locale.getDefault())
    private val inputFormats = listOf(
        SimpleDateFormat("yyyy-MM-dd", Locale.getDefault()),
        SimpleDateFormat("dd-MM-yyyy", Locale.getDefault()),
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()),
        SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.getDefault()),
        SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.getDefault())
    )

    fun formatDate(dateStr: String): String {
        if (dateStr.isBlank()) return dateStr
        for (fmt in inputFormats) {
            try {
                val date = fmt.parse(dateStr)
                if (date != null) return displayDateFormat.format(date)
            } catch (_: Exception) {}
        }
        return dateStr
    }

    fun formatTime(timeStr: String): String {
        if (timeStr.isBlank()) return timeStr
        try {
            val parts = timeStr.split(":")
            if (parts.size == 2) {
                val hour = parts[0].toIntOrNull() ?: return timeStr
                val min = parts[1].replace(Regex("[^0-9]"), "")
                val cal = Calendar.getInstance()
                cal.set(Calendar.HOUR_OF_DAY, hour)
                cal.set(Calendar.MINUTE, min.toIntOrNull() ?: 0)
                return displayTimeFormat.format(cal.time)
            }
        } catch (_: Exception) {}
        return timeStr
    }

    fun formatDateTime(dateTimeStr: String): String {
        if (dateTimeStr.isBlank()) return dateTimeStr
        for (fmt in inputFormats) {
            try {
                val date = fmt.parse(dateTimeStr)
                if (date != null) return displayDateTimeFormat.format(date)
            } catch (_: Exception) {}
        }
        return dateTimeStr
    }
}
