package com.pavancab.niranjan

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.pavancab.niranjan.data.Repository
import com.pavancab.niranjan.data.UserPrefs
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        CoroutineScope(Dispatchers.IO).launch {
            try { Repository(this@MyFirebaseMessagingService).saveFcmTokenToServer(token) } catch (_: Exception) {}
            try { UserPrefs.saveFcmToken(this@MyFirebaseMessagingService, token) } catch (_: Exception) {}
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        val title = message.notification?.title ?: message.data["title"] ?: "PAVANCAB"
        val body = message.notification?.body ?: message.data["body"] ?: ""
        val url = message.data["url"] ?: ""
        val bookingId = message.data["booking_id"] ?: ""
        val type = message.data["type"] ?: message.data["event"] ?: ""
        val reason = message.data["reason"] ?: ""
        val proposedFare = message.data["proposed_fare"] ?: ""

        if (type.uppercase() == "FARE_PROPOSED" && bookingId.isNotBlank()) {
            val enrichedBody = if (reason.isNotBlank() && proposedFare.isNotBlank()) {
                "Driver asking ₹$proposedFare. Reason: $reason. Accept or decline in the app."
            } else if (proposedFare.isNotBlank()) {
                "Driver asking ₹$proposedFare for your ride. Accept or decline in the app."
            } else {
                body
            }
            showFareProposalNotification(title, enrichedBody, bookingId)
        } else {
            showNotification(title, body, url, bookingId)
        }

        // Wake up any live screen so ride status updates immediately (no need to switch tabs/reopen)
        try {
            sendBroadcast(Intent("com.pavancab.niranjan.REFRESH_RIDES"))
        } catch (_: Exception) {}
    }

    private fun showFareProposalNotification(title: String, body: String, bookingId: String) {
        val channelId = "fare_proposals"
        val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        if (nm.getNotificationChannel(channelId) == null) {
            val ch = NotificationChannel(channelId, "Fare Proposals", NotificationManager.IMPORTANCE_HIGH)
            ch.description = "Fare proposal notifications with Accept/Decline"
            ch.enableVibration(true)
            ch.enableLights(true)
            nm.createNotificationChannel(ch)
        }

        val mainIntent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("booking_id", bookingId)
        }
        val mainPending = PendingIntent.getActivity(this, bookingId.hashCode(), mainIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val acceptIntent = Intent(this, FareProposalReceiver::class.java).apply {
            action = "ACTION_FARE_ACCEPT"
            putExtra("booking_id", bookingId)
            putExtra("notification_id", bookingId.hashCode().toString())
        }
        val acceptPending = PendingIntent.getBroadcast(this, bookingId.hashCode() + 1, acceptIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val declineIntent = Intent(this, FareProposalReceiver::class.java).apply {
            action = "ACTION_FARE_DECLINE"
            putExtra("booking_id", bookingId)
            putExtra("notification_id", bookingId.hashCode().toString())
        }
        val declinePending = PendingIntent.getBroadcast(this, bookingId.hashCode() + 2, declineIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val notification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(mainPending)
            .addAction(0, "ACCEPT", acceptPending)
            .addAction(0, "DECLINE", declinePending)
            .build()

        nm.notify(bookingId.hashCode(), notification)
    }

    private fun showNotification(title: String, body: String, url: String, bookingId: String) {
        val channelId = "default"
        val nm = getSystemService(NOTIFICATION_SERVICE) as NotificationManager

        if (nm.getNotificationChannel(channelId) == null) {
            val ch = NotificationChannel(channelId, "Ride Updates", NotificationManager.IMPORTANCE_HIGH)
            ch.description = "PAVANCAB ride notifications"
            nm.createNotificationChannel(ch)
        }

        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("booking_id", bookingId)
            if (url.isNotEmpty()) putExtra("url", url)
        }

        val pending = PendingIntent.getActivity(this, System.currentTimeMillis().toInt(), intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

        val notification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pending)
            .build()

        nm.notify(System.currentTimeMillis().toInt(), notification)
    }
}
