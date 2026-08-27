package com.pavancab.dispatch.worker

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Locale
import java.util.TimeZone

class ReminderAlarmReceiver : BroadcastReceiver() {

    override fun onReceive(ctx: Context, intent: Intent) {
        val bookingId = intent.getIntExtra("booking_id", 0)
        val bookingRef = intent.getStringExtra("booking_ref") ?: ""
        val reminderType = intent.getStringExtra("reminder_type") ?: "ride_soon"
        val customerPhone = intent.getStringExtra("customer_phone") ?: ""
        val customerEmail = intent.getStringExtra("customer_email") ?: ""
        val customerName = intent.getStringExtra("customer_name") ?: "Passenger"
        val driverPhone = intent.getStringExtra("driver_phone") ?: ""
        val driverName = intent.getStringExtra("driver_name") ?: "Driver"
        val pickup = intent.getStringExtra("pickup") ?: ""
        val drop = intent.getStringExtra("drop") ?: ""
        val cab = intent.getStringExtra("cab") ?: ""
        val fare = intent.getStringExtra("fare") ?: "0"
        val dateStr = intent.getStringExtra("date") ?: ""
        val timeStr = intent.getStringExtra("time") ?: ""

        Log.d("RideAlarm", "ALARM FIRED for #$bookingRef type=$reminderType")

        val pendingResult = goAsync()

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val isAdmin = UserPrefs.isAdmin(ctx)
                if (!isAdmin) {
                    Log.d("RideAlarm", "Not admin, skipping")
                    pendingResult.finish()
                    return@launch
                }

                val repo = Repository(ctx)

                // Re-check ride status before sending — skip if cancelled/completed
                val current = repo.getBookingDetail(bookingId)
                if (current != null && current.status.uppercase() in listOf("CANCELLED", "COMPLETED", "NO_SHOW")) {
                    Log.d("RideAlarm", "Ride #$bookingRef is ${current.status}, skipping reminder")
                    pendingResult.finish()
                    return@launch
                }
                val status = current?.status?.uppercase() ?: ""

                val waFooter = ""

                when (reminderType) {
                    "unassigned", "unassigned_urgent" -> {
                        val urgency = if (reminderType == "unassigned_urgent") "⚠️ URGENT" else "⏳"
                        val teamMsg = "$urgency *UNASSIGNED RIDE - #$bookingRef*\n\nCustomer: *$customerName*\nPhone: *$customerPhone*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\n👉 Assign a driver NOW in Dispatch Tower!$waFooter"

                        try {
                            repo.sendPersonalPush("+918180951176", "", "$urgency Ride Needs Driver - #$bookingRef", teamMsg)
                            Log.d("RideAlarm", "Sent unassigned push to admin for #$bookingRef")
                        } catch (e: Exception) { Log.e("RideAlarm", "Admin push failed: ${e.message}") }

                        try {
                            val teamPhones = repo.getTeamMembers().mapNotNull { m ->
                                val p = m.memberPhone.replace(" ", "").trim()
                                if (p.isNotBlank() && p.length >= 10) if (p.startsWith("+")) p else "+91$p" else null
                            }
                            if (teamPhones.isNotEmpty()) {
                                repo.bulkWhatsApp(teamPhones, teamMsg)
                                Log.d("RideAlarm", "Sent unassigned WhatsApp to ${teamPhones.size} team for #$bookingRef")
                            }
                        } catch (e: Exception) { Log.e("RideAlarm", "Bulk WA failed: ${e.message}") }

                        try { repo.markReminderSent(bookingId) } catch (e: Exception) { Log.e("RideAlarm", "markReminderSent failed: ${e.message}") }
                    }

                    "night_ride" -> {
                        val teamMsg = "🌙 *NIGHT RIDE ALERT - #$bookingRef*\n\nCustomer: *$customerName*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\n⚠️ Please assign a driver before sleep!\n\n👉 Open Dispatch Tower to assign.$waFooter"

                        try {
                            repo.sendPersonalPush("+918180951176", "", "Night Ride Alert - #$bookingRef", teamMsg)
                            Log.d("RideAlarm", "Sent night push to admin for #$bookingRef")
                        } catch (e: Exception) { Log.e("RideAlarm", "Night admin push failed: ${e.message}") }

                        try {
                            val teamPhones = repo.getTeamMembers().mapNotNull { m ->
                                val p = m.memberPhone.replace(" ", "").trim()
                                if (p.isNotBlank() && p.length >= 10) if (p.startsWith("+")) p else "+91$p" else null
                            }
                            if (teamPhones.isNotEmpty()) {
                                repo.bulkWhatsApp(teamPhones, teamMsg)
                                Log.d("RideAlarm", "Sent night WhatsApp to ${teamPhones.size} team for #$bookingRef")
                            }
                        } catch (e: Exception) { Log.e("RideAlarm", "Night WA failed: ${e.message}") }

                        if (customerPhone.isNotBlank() && customerPhone.length >= 10) {
                            try {
                                val phone = if (customerPhone.startsWith("+")) customerPhone else "+91$customerPhone"
                                val customerMsg = "🌙 *PAVANCAB RIDE REMINDER*\n\nHi *$customerName*!\n\nYour night ride is scheduled.\n\nRef: *#$bookingRef*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\nWe'll assign your driver soon.$waFooter"
                                repo.sendPersonalPush(phone, customerEmail, "Night Ride Reminder - #$bookingRef", customerMsg)
                                Log.d("RideAlarm", "Sent night push to customer for #$bookingRef")
                            } catch (e: Exception) { Log.e("RideAlarm", "Night customer push failed: ${e.message}") }
                        }

                        try { repo.markReminderSent(bookingId) } catch (e: Exception) { Log.e("RideAlarm", "markReminderSent failed: ${e.message}") }
                    }

                    else -> {
                        if (customerPhone.isNotBlank() && customerPhone.length >= 10) {
                            try {
                                val phone = if (customerPhone.startsWith("+")) customerPhone else "+91$customerPhone"
                                val customerMsg = "🚕 *PAVANCAB RIDE REMINDER*\n\nHi *$customerName*!\n\nThis is a reminder for your upcoming ride.\n\nRef: *#$bookingRef*\nCab: *$cab*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nFare: *₹$fare*\n\nTo cancel, WhatsApp or call +918180951176$waFooter"
                                repo.sendPersonalPush(phone, customerEmail, "Ride Reminder - #$bookingRef", customerMsg)
                                Log.d("RideAlarm", "Sent ride_soon push to customer for #$bookingRef")
                            } catch (e: Exception) { Log.e("RideAlarm", "Customer push failed: ${e.message}") }
                        }

                        if (driverPhone.isNotBlank() && driverPhone.length >= 10) {
                            try {
                                val phone = if (driverPhone.startsWith("+")) driverPhone else "+91$driverPhone"
                                val driverMsg = "🚕 *PAVANCAB RIDE REMINDER*\n\nHi *$driverName*!\n\nYou have an upcoming ride.\n\nRef: *#$bookingRef*\nPassenger: *$customerName*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nFare: *₹$fare*$waFooter"
                                repo.sendPersonalPush(phone, "", "Ride Reminder - #$bookingRef", driverMsg)
                                Log.d("RideAlarm", "Sent ride_soon push to driver for #$bookingRef")
                            } catch (e: Exception) { Log.e("RideAlarm", "Driver push failed: ${e.message}") }
                        }

                        try { repo.markReminderSent(bookingId) } catch (e: Exception) { Log.e("RideAlarm", "markReminderSent failed: ${e.message}") }
                    }
                }
            } catch (e: Exception) {
                Log.e("RideAlarm", "Receiver failed: ${e.message}")
            } finally {
                pendingResult.finish()
            }
        }
    }

    companion object {
        fun scheduleAlarm(ctx: Context, bookingId: Int, bookingRef: String, reminderType: String,
                          customerPhone: String, customerEmail: String, customerName: String,
                          driverPhone: String, driverName: String,
                          pickup: String, drop: String, cab: String, fare: String,
                          dateStr: String, timeStr: String, triggerAtMillis: Long) {
            val am = ctx.getSystemService(Context.ALARM_SERVICE) as AlarmManager

            val intent = Intent(ctx, ReminderAlarmReceiver::class.java).apply {
                putExtra("booking_id", bookingId)
                putExtra("booking_ref", bookingRef)
                putExtra("reminder_type", reminderType)
                putExtra("customer_phone", customerPhone)
                putExtra("customer_email", customerEmail)
                putExtra("customer_name", customerName)
                putExtra("driver_phone", driverPhone)
                putExtra("driver_name", driverName)
                putExtra("pickup", pickup)
                putExtra("drop", drop)
                putExtra("cab", cab)
                putExtra("fare", fare)
                putExtra("date", dateStr)
                putExtra("time", timeStr)
            }

            val requestCode = bookingId * 10 + when (reminderType) {
                "unassigned" -> 1
                "unassigned_urgent" -> 2
                "night_ride" -> 3
                else -> 0
            }

            val pi = PendingIntent.getBroadcast(ctx, requestCode, intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                if (am.canScheduleExactAlarms()) {
                    am.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAtMillis, pi)
                } else {
                    am.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAtMillis, pi)
                }
            } else {
                am.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAtMillis, pi)
            }

            val sdf = SimpleDateFormat("dd-MM-yyyy hh:mm:ss a", Locale.US).apply { timeZone = TimeZone.getDefault() }
            Log.d("RideAlarm", "Scheduled alarm for #$bookingRef ($reminderType) at ${sdf.format(triggerAtMillis)} (in ${((triggerAtMillis - System.currentTimeMillis()) / 60000)} min)")
        }

        fun cancelAlarm(ctx: Context, bookingId: Int, reminderType: String) {
            val am = ctx.getSystemService(Context.ALARM_SERVICE) as AlarmManager
            val requestCode = bookingId * 10 + when (reminderType) {
                "unassigned" -> 1
                "unassigned_urgent" -> 2
                "night_ride" -> 3
                else -> 0
            }
            val intent = Intent(ctx, ReminderAlarmReceiver::class.java)
            val pi = PendingIntent.getBroadcast(ctx, requestCode, intent,
                PendingIntent.FLAG_NO_CREATE or PendingIntent.FLAG_IMMUTABLE)
            if (pi != null) {
                am.cancel(pi)
                pi.cancel()
                Log.d("RideAlarm", "Cancelled alarm for bookingId=$bookingId type=$reminderType")
            }
        }
    }
}
