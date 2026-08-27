package com.pavancab.niranjan

import android.app.NotificationManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.widget.Toast
import com.pavancab.niranjan.data.Repository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class FareProposalReceiver : BroadcastReceiver() {

    companion object {
        // Double-tap / duplicate delivery guard
        private val handled = java.util.Collections.synchronizedSet(mutableSetOf<String>())
    }

    override fun onReceive(context: Context, intent: Intent) {
        val pendingResult = goAsync()
        val bookingId = intent.getStringExtra("booking_id")?.toIntOrNull() ?: 0
        val notificationId = intent.getStringExtra("notification_id")?.toIntOrNull() ?: 0
        val action = intent.action

        if (bookingId == 0) {
            pendingResult.finish()
            return
        }

        val response = when (action) {
            "ACTION_FARE_ACCEPT" -> "ACCEPTED"
            "ACTION_FARE_DECLINE" -> "DECLINED"
            else -> {
                pendingResult.finish()
                return
            }
        }

        if (!handled.add("$bookingId-$response")) {
            pendingResult.finish()
            return
        }

        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        nm.cancel(notificationId)

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val result = Repository(context).respondFareProposal(bookingId, response)
                val success = result.get("success")?.asBoolean ?: false
                val message = result.get("message")?.asString ?: ""
                CoroutineScope(Dispatchers.Main).launch {
                    Toast.makeText(context, message.ifBlank { if (response == "ACCEPTED") "Fare accepted!" else "Fare declined" }, Toast.LENGTH_SHORT).show()
                }
            } catch (e: Exception) {
                CoroutineScope(Dispatchers.Main).launch {
                    Toast.makeText(context, "Failed to respond: ${e.message ?: "Unknown error"}", Toast.LENGTH_SHORT).show()
                }
            } finally {
                pendingResult.finish()
            }
        }
    }
}
