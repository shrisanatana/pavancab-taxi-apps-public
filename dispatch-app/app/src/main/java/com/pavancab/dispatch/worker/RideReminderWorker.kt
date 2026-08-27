package com.pavancab.dispatch.worker

import android.content.Context
import android.util.Log
import androidx.work.*
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.concurrent.TimeUnit

class RideReminderWorker(
    context: Context,
    params: WorkerParameters
) : CoroutineWorker(context, params) {

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        try {
            val isAdmin = UserPrefs.isAdmin(applicationContext)
            if (!isAdmin) {
                Log.d("RideReminder", "Not admin, skipping")
                return@withContext Result.success()
            }

            val repo = Repository(applicationContext)
            val upcomingRides = repo.getUpcomingRidesNeedingReminders()
            Log.d("RideReminder", "Found ${upcomingRides.size} rides needing attention")

            for (ride in upcomingRides) {
                try {
                    // Re-check ride status before sending — skip if cancelled/completed
                    val current = repo.getBookingDetail(ride.id)
                    if (current != null && current.status.uppercase() in listOf("CANCELLED", "COMPLETED", "NO_SHOW")) {
                        Log.d("RideReminder", "Ride #${ride.bookingRef} is ${current.status}, skipping")
                        continue
                    }

                    val ref = ride.bookingRef
                    val customerPhone = ride.customerPhone.replace("+", "").replace(" ", "").trim()
                    val driverPhone = ride.driverPhone.replace("+", "").replace(" ", "").trim()
                    val customerEmail = ride.userEmail
                    val customerName = ride.customerName.ifBlank { "Passenger" }
                    val driverName = ride.driverName.ifBlank { "Driver" }
                    val pickup = ride.pickupLocation
                    val drop = ride.dropLocation
                    val cab = ride.cabType
                    val fare = ride.totalFare.toInt()
                    val dateStr = ride.pickupDate
                    val timeStr = ride.pickupTime
                    val reminderType = ride.reminderType

                    val waFooter = "\n\n---\nFor more info WhatsApp +918180951176"

                    when (reminderType) {
                        "unassigned", "unassigned_urgent" -> {
                            val urgency = if (reminderType == "unassigned_urgent") "⚠️ URGENT" else "⏳"
                            val teamMsg = "$urgency *UNASSIGNED RIDE - #$ref*\n\nCustomer: *$customerName*\nPhone: *$customerPhone*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\n👉 Assign a driver NOW in Dispatch Tower!$waFooter"

                            try {
                                val superAdminPhone = "+918180951176"
                                repo.sendPersonalPush(superAdminPhone, "", "$urgency Ride Needs Driver - #$ref", teamMsg)
                                Log.d("RideReminder", "Sent unassigned push to admin for #$ref")
                            } catch (e: Exception) {
                                Log.e("RideReminder", "Failed push to admin: ${e.message}")
                            }

                            try {
                                val teamPhones = mutableListOf<String>()
                                val members = repo.getTeamMembers()
                                for (m in members) {
                                    val p = m.memberPhone.replace(" ", "").trim()
                                    if (p.isNotBlank() && p.length >= 10) {
                                        teamPhones.add(if (p.startsWith("+")) p else "+91$p")
                                    }
                                }
                                if (teamPhones.isNotEmpty()) {
                                    repo.bulkWhatsApp(teamPhones, teamMsg)
                                    Log.d("RideReminder", "Sent unassigned WhatsApp to ${teamPhones.size} team members for #$ref")
                                }
                            } catch (e: Exception) {
                                Log.e("RideReminder", "Failed bulk WhatsApp: ${e.message}")
                            }

                            try { repo.markReminderSent(ride.id) } catch (e: Exception) {
                                Log.e("RideReminder", "Failed mark-reminder-sent: ${e.message}")
                            }
                        }

                        "night_ride" -> {
                            val teamMsg = "🌙 *NIGHT RIDE ALERT - #$ref*\n\nCustomer: *$customerName*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\n⚠️ Please assign a driver before sleep!\n\n👉 Open Dispatch Tower to assign.$waFooter"

                            try {
                                repo.sendPersonalPush("+918180951176", "", "Night Ride Alert - #$ref", teamMsg)
                                Log.d("RideReminder", "Sent night ride push to admin for #$ref")
                            } catch (e: Exception) {
                                Log.e("RideReminder", "Failed night push to admin: ${e.message}")
                            }

                            try {
                                val teamPhones = mutableListOf<String>()
                                val members = repo.getTeamMembers()
                                for (m in members) {
                                    val p = m.memberPhone.replace(" ", "").trim()
                                    if (p.isNotBlank() && p.length >= 10) {
                                        teamPhones.add(if (p.startsWith("+")) p else "+91$p")
                                    }
                                }
                                if (teamPhones.isNotEmpty()) {
                                    repo.bulkWhatsApp(teamPhones, teamMsg)
                                    Log.d("RideReminder", "Sent night ride WhatsApp to ${teamPhones.size} team for #$ref")
                                }
                            } catch (e: Exception) {
                                Log.e("RideReminder", "Failed night WhatsApp: ${e.message}")
                            }

                            if (customerPhone.isNotBlank() && customerPhone.length >= 10) {
                                try {
                                    val phone = if (customerPhone.startsWith("+")) customerPhone else "+91$customerPhone"
                                    val customerMsg = "🌙 *PAVANCAB RIDE REMINDER*\n\nHi *$customerName*!\n\nYour night ride is scheduled.\n\nRef: *#$ref*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nCab: *$cab*\nFare: *₹$fare*\n\nWe'll assign your driver soon.$waFooter"
                                    repo.sendPersonalPush(phone, customerEmail, "Night Ride Reminder - #$ref", customerMsg)
                                    Log.d("RideReminder", "Sent night ride push to customer for #$ref")
                                } catch (e: Exception) {
                                    Log.e("RideReminder", "Failed night push to customer: ${e.message}")
                                }
                            }

                            try { repo.markReminderSent(ride.id) } catch (e: Exception) {
                                Log.e("RideReminder", "Failed mark-reminder-sent: ${e.message}")
                            }
                        }

                        else -> {
                            if (customerPhone.isNotBlank() && customerPhone.length >= 10) {
                                try {
                                    val phone = if (customerPhone.startsWith("+")) customerPhone else "+91$customerPhone"
                                    val customerMsg = "🚕 *PAVANCAB RIDE REMINDER*\n\nHi *$customerName*!\n\nThis is a reminder for your upcoming ride.\n\nRef: *#$ref*\nCab: *$cab*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nFare: *₹$fare*\n\nTo cancel, WhatsApp or call +918180951176$waFooter"
                                    repo.sendPersonalPush(phone, customerEmail, "Ride Reminder - #$ref", customerMsg)
                                    Log.d("RideReminder", "Sent ride_soon push to customer for #$ref")
                                } catch (e: Exception) {
                                    Log.e("RideReminder", "Failed ride_soon customer push: ${e.message}")
                                }
                            }

                            if (driverPhone.isNotBlank() && driverPhone.length >= 10) {
                                try {
                                    val phone = if (driverPhone.startsWith("+")) driverPhone else "+91$driverPhone"
                                    val driverMsg = "🚕 *PAVANCAB RIDE REMINDER*\n\nHi *$driverName*!\n\nYou have an upcoming ride.\n\nRef: *#$ref*\nPassenger: *$customerName*\nPickup: *$pickup*\nDrop: *$drop*\nDate: *$dateStr*\nTime: *$timeStr*\nFare: *₹$fare*$waFooter"
                                    repo.sendPersonalPush(phone, "", "Ride Reminder - #$ref", driverMsg)
                                    Log.d("RideReminder", "Sent ride_soon push to driver for #$ref")
                                } catch (e: Exception) {
                                    Log.e("RideReminder", "Failed ride_soon driver push: ${e.message}")
                                }
                            }

                            try { repo.markReminderSent(ride.id) } catch (e: Exception) {
                                Log.e("RideReminder", "Failed mark-reminder-sent: ${e.message}")
                            }
                        }
                    }
                } catch (e: Exception) {
                    Log.e("RideReminder", "Failed for ride ${ride.id}: ${e.message}")
                }
            }
            Result.success()
        } catch (e: Exception) {
            Log.e("RideReminder", "Worker failed: ${e.message}")
            Result.success()
        }
    }

    companion object {
        private const val WORK_NAME = "ride_reminder_work"

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<RideReminderWorker>(
                15, TimeUnit.MINUTES
            ).setConstraints(
                Constraints.Builder()
                    .setRequiredNetworkType(NetworkType.CONNECTED)
                    .build()
            ).build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }

        fun cancel(context: Context) {
            WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
        }
    }
}
