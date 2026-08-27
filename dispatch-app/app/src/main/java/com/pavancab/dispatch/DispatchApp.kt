package com.pavancab.dispatch

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.net.Uri
import com.google.firebase.messaging.FirebaseMessaging
import com.pavancab.dispatch.network.ApiClient
import com.pavancab.dispatch.data.UserPrefs
import com.pavancab.dispatch.worker.RideReminderWorker
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class DispatchApp : Application() {
    override fun onCreate() {
        super.onCreate()
        ApiClient.appContext = applicationContext
        Thread.setDefaultUncaughtExceptionHandler(CrashLogger(this))

        // Restore session for WorkManager workers that run in background
        kotlinx.coroutines.runBlocking {
            try {
                val saved = UserPrefs.getSession(applicationContext)
                if (!saved.isNullOrBlank()) {
                    ApiClient.cookieJar.restoreSession(saved)
                }
            } catch (_: Exception) {}
        }

        createNotificationChannels()
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) task.result?.let { CrashLogger.log("FCM", it, "DispatchApp") }
        }
        try { RideReminderWorker.schedule(this) } catch (_: Exception) {}

        // Schedule exact alarms for all upcoming rides on app startup
        CoroutineScope(kotlinx.coroutines.Dispatchers.IO).launch {
            try {
                val saved = UserPrefs.getSession(applicationContext)
                if (!saved.isNullOrBlank()) {
                    ApiClient.cookieJar.restoreSession(saved)
                    val isAdmin = UserPrefs.isAdmin(applicationContext)
                    if (isAdmin) {
                        val repo = com.pavancab.dispatch.data.Repository(applicationContext)
                        val rides = repo.getUpcomingRidesNeedingReminders()
                        com.pavancab.dispatch.worker.AlarmScheduler.scheduleForRides(applicationContext, rides)
                    }
                }
            } catch (_: Exception) {}
        }
    }

    private fun createNotificationChannels() {
        val mgr = getSystemService(NotificationManager::class.java) ?: return
        val sp = getSharedPreferences("dispatch_session", MODE_PRIVATE)

        val notifAttr = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()

        fun resolveUri(saved: String, fallback: Uri): Uri {
            if (saved.isBlank() || saved == Uri.EMPTY.toString() || saved.endsWith("/null")) return fallback
            return try { Uri.parse(saved) } catch (_: Exception) { fallback }
        }

        // 1. New booking — user's custom ringtone
        val savedNewUri = sp.getString("ringtone_uri", "") ?: ""
        val newBookingUri = resolveUri(savedNewUri, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.new_booking}"))
        val repeat = sp.getInt("ringtone_repeat", 3)
        val hash = "${savedNewUri}_${repeat}".hashCode()
        val newBookingChannelId = "dispatch_new_booking_$hash"

        // 2. Phone booking — user's phone booking tone
        val savedPhoneUri = sp.getString("ringtone_uri_phone", "") ?: ""
        val phoneBookingUri = resolveUri(savedPhoneUri, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.phone_booking}"))

        // 3. Booking confirmed — built-in chime
        val confirmedUri = Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.booking_confirmed}")

        // 4. Booking cancelled — user's cancelled tone
        val savedCancelledUri = sp.getString("ringtone_uri_cancelled", "") ?: ""
        val cancelledUri = resolveUri(savedCancelledUri, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.booking_cancelled}"))

        val channels = listOf(
            NotificationChannel("dispatch_default", "General Notifications", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Dispatch app notifications"
            },
            NotificationChannel(newBookingChannelId, "New Booking Alerts", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alerts for new bookings from app users"
                enableVibration(true); enableLights(true)
                vibrationPattern = longArrayOf(500, 300, 500, 300, 500)
                setSound(newBookingUri, notifAttr)
            },
            NotificationChannel("dispatch_phone_booking", "Phone Bookings", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alerts for bookings placed by phone"
                enableVibration(true); enableLights(true)
                vibrationPattern = longArrayOf(400, 200, 400)
                setSound(phoneBookingUri, notifAttr)
            },
            NotificationChannel("dispatch_booking_confirmed", "Booking Confirmed", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alert when a booking is confirmed with driver"
                enableVibration(true); enableLights(true)
                vibrationPattern = longArrayOf(300, 200, 300)
                setSound(confirmedUri, notifAttr)
            },
            NotificationChannel("dispatch_booking_cancelled", "Booking Cancelled", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alert when a booking is cancelled"
                enableVibration(true); enableLights(true)
                vibrationPattern = longArrayOf(200, 100, 200, 100, 200)
                setSound(cancelledUri, notifAttr)
            }
        )
        channels.forEach { mgr.createNotificationChannel(it) }

        sp.edit().putString("active_new_booking_channel", newBookingChannelId).apply()
        try { mgr.deleteNotificationChannel("dispatch_ringtone") } catch (_: Exception) {}
    }
}
