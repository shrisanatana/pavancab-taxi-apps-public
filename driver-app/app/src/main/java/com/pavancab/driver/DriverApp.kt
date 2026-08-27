package com.pavancab.driver

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.media.AudioAttributes
import android.net.Uri
import com.google.firebase.messaging.FirebaseMessaging
import com.pavancab.driver.data.UserPrefs
import com.pavancab.driver.network.ApiClient
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class DriverApp : Application() {
    override fun onCreate() {
        super.onCreate()
        ApiClient.appContext = applicationContext
        Thread.setDefaultUncaughtExceptionHandler(CrashLogger(this))

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
            if (task.isSuccessful) task.result?.let { CrashLogger.log("FCM", it, "DriverApp") }
        }
    }

    private fun createNotificationChannels() {
        val mgr = getSystemService(NotificationManager::class.java) ?: return

        val notifAttr = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()

        val channels = listOf(
            NotificationChannel("driver_default", "General", NotificationManager.IMPORTANCE_DEFAULT),
            NotificationChannel("driver_new_ride", "New Ride Alert", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Alert for new ride assignments"
                enableVibration(true)
                vibrationPattern = longArrayOf(500, 300, 500, 300, 500)
                setSound(Uri.parse("android.resource://com.pavancab.driver/${R.raw.new_booking}"), notifAttr)
            },
            NotificationChannel("driver_trip_update", "Trip Updates", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Trip status updates"
                enableVibration(true)
                vibrationPattern = longArrayOf(300, 200, 300)
            }
        )
        channels.forEach { mgr.createNotificationChannel(it) }
    }
}
