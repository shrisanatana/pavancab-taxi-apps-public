package com.pavancab.niranjan.utils

import java.text.SimpleDateFormat
import java.util.*

object DateUtils {
    private const val DISPLAY_DATE_PATTERN = "dd-MM-yyyy"
    private const val DISPLAY_TIME_PATTERN = "hh:mm a"
    private const val DISPLAY_DATE_TIME_PATTERN = "dd-MM-yyyy, hh:mm a"
    private val inputPatterns = listOf(
        "yyyy-MM-dd",
        "dd-MM-yyyy",
        "yyyy-MM-dd HH:mm:ss",
        "yyyy-MM-dd'T'HH:mm:ss",
        "yyyy-MM-dd HH:mm"
    )

    private fun newFormat(pattern: String): SimpleDateFormat = SimpleDateFormat(pattern, Locale.getDefault())

    fun formatDate(dateStr: String): String {
        if (dateStr.isBlank()) return dateStr
        for (pattern in inputPatterns) {
            try {
                val date = newFormat(pattern).parse(dateStr)
                if (date != null) return newFormat(DISPLAY_DATE_PATTERN).format(date)
            } catch (_: Exception) {}
        }
        return dateStr
    }

    fun formatTime(timeStr: String): String {
        if (timeStr.isBlank()) return timeStr
        // Handle HH:mm (24hr) -> 12hr AM/PM
        try {
            val parts = timeStr.split(":")
            if (parts.size == 2) {
                val hour = parts[0].toIntOrNull() ?: return timeStr
                val min = parts[1].replace(Regex("[^0-9]"), "")
                val cal = Calendar.getInstance()
                cal.set(Calendar.HOUR_OF_DAY, hour)
                cal.set(Calendar.MINUTE, min.toIntOrNull() ?: 0)
                return newFormat(DISPLAY_TIME_PATTERN).format(cal.time)
            }
        } catch (_: Exception) {}
        return timeStr
    }

    fun formatDateTime(dateTimeStr: String): String {
        if (dateTimeStr.isBlank()) return dateTimeStr
        for (pattern in inputPatterns) {
            try {
                val date = newFormat(pattern).parse(dateTimeStr)
                if (date != null) return newFormat(DISPLAY_DATE_TIME_PATTERN).format(date)
            } catch (_: Exception) {}
        }
        return dateTimeStr
    }
}
