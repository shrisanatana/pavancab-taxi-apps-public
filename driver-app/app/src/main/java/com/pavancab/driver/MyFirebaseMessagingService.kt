package com.pavancab.driver

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.net.Uri
import android.os.Handler
import android.os.Looper
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onMessageReceived(message: RemoteMessage) {
        val title = message.notification?.title ?: message.data["title"] ?: "Driver Alert"
        val body = message.notification?.body ?: message.data["body"] ?: ""
        val type = message.data["type"] ?: message.data["event"] ?: ""

        val channelId = when (type.uppercase()) {
            "NEW_RIDE", "RIDE_ASSIGNED", "BOOKING_ASSIGNED" -> "driver_new_ride"
            "TRIP_UPDATE", "BOOKING_CANCELLED" -> "driver_trip_update"
            else -> "driver_default"
        }

        showNotification(channelId, title, body)

        if (channelId == "driver_new_ride") {
            playAlertRingtone()
        }

        // Refresh data
        val refreshIntent = Intent("com.pavancab.driver.REFRESH_DATA").apply {
            setPackage(packageName)
            putExtra("type", type)
        }
        sendBroadcast(refreshIntent)
    }

    override fun onNewToken(token: String) {
        CrashLogger.log("FCM_TOKEN", token, "DriverFCM")
        CoroutineScope(Dispatchers.IO).launch {
            try {
                val phone = com.pavancab.driver.data.UserPrefs.getPhone(applicationContext)
                val params = mutableMapOf("fcm_token" to token)
                if (!phone.isNullOrBlank()) params["phone"] = phone
                com.pavancab.driver.network.ApiClient.rawPost("api/driver.php?action=save-fcm-token", params)
            } catch (_: Exception) {}
        }
    }

    private fun showNotification(channelId: String, title: String, body: String) {
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
        }
        val pending = PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)

        val notification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setVibrate(longArrayOf(500, 300, 500, 300, 500))
            .setContentIntent(pending)
            .build()

        val mgr = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        mgr.notify(System.currentTimeMillis().toInt(), notification)
    }

    private fun playAlertRingtone() {
        try {
            val uri = Uri.parse("android.resource://com.pavancab.driver/${R.raw.new_booking}")
            val handler = Handler(Looper.getMainLooper())
            var played = 0
            fun playOnce() {
                try {
                    val mp = MediaPlayer()
                    mp.setDataSource(applicationContext, uri)
                    mp.setAudioAttributes(AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION).build())
                    mp.setOnCompletionListener { mp2 -> mp2.release(); played++; if (played < 3) handler.postDelayed({ playOnce() }, 300) }
                    mp.setOnErrorListener { mp2, _, _ -> mp2.release(); played++; if (played < 3) handler.postDelayed({ playOnce() }, 300); true }
                    mp.prepare(); mp.start()
                } catch (_: Exception) {}
            }
            playOnce()
        } catch (_: Exception) {}
    }
}
