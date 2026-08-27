package com.pavancab.dispatch

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.RingtoneManager
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
        val title = message.notification?.title ?: message.data["title"] ?: "Dispatch Alert"
        val body = message.notification?.body ?: message.data["body"] ?: ""
        val type = message.data["type"] ?: message.data["event"] ?: ""

        val sp = getSharedPreferences("dispatch_session", Context.MODE_PRIVATE)
        val repeatCount = sp.getInt("ringtone_repeat", 3)

        val channelId = resolveChannelId(type, sp)
        showNotification(channelId, title, body, type)

        val ringtoneUri = resolveRingtoneUri(type, sp)
        playRingtoneWithRepeat(ringtoneUri, repeatCount)

        val refreshIntent = Intent("com.pavancab.dispatch.REFRESH_DATA").apply {
            setPackage(packageName)
            putExtra("type", type)
            putExtra("title", title)
            putExtra("body", body)
        }
        sendBroadcast(refreshIntent)

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val repo = com.pavancab.dispatch.data.Repository(applicationContext)
                val rides = repo.getUpcomingRidesNeedingReminders()
                com.pavancab.dispatch.worker.AlarmScheduler.scheduleForRides(applicationContext, rides)
            } catch (_: Exception) {}
        }
    }

    override fun onNewToken(token: String) {
        CrashLogger.log("FCM_TOKEN", token, "DispatchFCM")
        CoroutineScope(Dispatchers.IO).launch {
            try {
                com.pavancab.dispatch.network.ApiClient.rawPost("api/dispatch.php?action=save_fcm_token", mapOf("fcm_token" to token))
            } catch (_: Exception) {}
        }
    }

    private fun resolveChannelId(type: String, sp: android.content.SharedPreferences): String {
        return when (type.uppercase()) {
            "BOOKING_CONFIRMED", "DRIVER_ASSIGNED", "FARE_ACCEPTED" -> "dispatch_booking_confirmed"
            "BOOKING_CANCELLED", "RIDE_CANCELLED", "FARE_DECLINED" -> "dispatch_booking_cancelled"
            "PHONE_BOOKING" -> "dispatch_phone_booking"
            else -> {
                val savedUri = sp.getString("ringtone_uri", "") ?: ""
                val repeat = sp.getInt("ringtone_repeat", 3)
                val hash = "${savedUri}_${repeat}".hashCode()
                val channelId = "dispatch_new_booking_$hash"
                ensureNewBookingChannel(channelId, sp)
                channelId
            }
        }
    }

    private fun ensureNewBookingChannel(channelId: String, sp: android.content.SharedPreferences) {
        val mgr = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (mgr.getNotificationChannel(channelId) != null) return

        val savedUri = sp.getString("ringtone_uri", "") ?: ""
        val ringtoneUri = resolveUri(savedUri, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION))

        val channel = NotificationChannel(channelId, "New Booking Alerts", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Ringtone alerts for new bookings from users"
            enableVibration(true)
            enableLights(true)
            vibrationPattern = longArrayOf(500, 300, 500, 300, 500)
            setSound(ringtoneUri, AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build())
        }
        mgr.createNotificationChannel(channel)
    }

    private fun resolveRingtoneUri(type: String, sp: android.content.SharedPreferences): Uri {
        return when (type.uppercase()) {
            "BOOKING_CONFIRMED", "DRIVER_ASSIGNED", "FARE_ACCEPTED" ->
                Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.booking_confirmed}")
            "BOOKING_CANCELLED", "RIDE_CANCELLED", "FARE_DECLINED" -> {
                val saved = sp.getString("ringtone_uri_cancelled", "") ?: ""
                resolveUri(saved, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.booking_cancelled}"))
            }
            "PHONE_BOOKING" -> {
                val saved = sp.getString("ringtone_uri_phone", "") ?: ""
                resolveUri(saved, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.phone_booking}"))
            }
            else -> {
                val saved = sp.getString("ringtone_uri", "") ?: ""
                resolveUri(saved, Uri.parse("android.resource://com.pavancab.dispatch/${R.raw.new_booking}"))
            }
        }
    }

    private fun resolveUri(saved: String, fallback: Uri): Uri {
        if (saved.isBlank() || saved == Uri.EMPTY.toString() || saved.endsWith("/null")) return fallback
        return try { Uri.parse(saved) } catch (_: Exception) { fallback }
    }

    private fun showNotification(channelId: String, title: String, body: String, type: String) {
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

    private fun playRingtoneWithRepeat(uri: Uri, repeatCount: Int) {
        val handler = Handler(Looper.getMainLooper())
        var played = 0

        fun playOnce() {
            try {
                val mp = MediaPlayer()
                mp.setDataSource(applicationContext, uri)
                mp.setAudioAttributes(AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build())
                mp.setOnCompletionListener { mp2 -> mp2.release(); played++; if (played < repeatCount) handler.postDelayed({ playOnce() }, 300) }
                mp.setOnErrorListener { mp2, _, _ -> mp2.release(); played++; if (played < repeatCount) handler.postDelayed({ playOnce() }, 300); true }
                mp.prepare()
                mp.start()
            } catch (_: Exception) {}
        }
        playOnce()
    }
}
