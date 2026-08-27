package com.pavancab.dispatch.worker

import android.content.Context
import android.util.Log
import com.pavancab.dispatch.model.Booking
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import java.util.TimeZone

object AlarmScheduler {

    private const val TAG = "AlarmScheduler"

    fun scheduleForRide(ctx: Context, ride: Booking) {
        if (ride.pickupDate.isBlank() || ride.pickupTime.isBlank()) return

        val pickupTs = try {
            val fmt = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).apply { timeZone = TimeZone.getDefault() }
            fmt.parse("${ride.pickupDate} ${ride.pickupTime}")?.time ?: return
        } catch (e: Exception) { return }

        val now = System.currentTimeMillis()
        val bookingAge = now - (try {
            val fmt = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).apply { timeZone = TimeZone.getDefault() }
            fmt.parse(ride.createdAt)?.time ?: now
        } catch (e: Exception) { now })

        val customerPhone = ride.customerPhone.replace("+", "").replace(" ", "").trim()
        val driverPhone = ride.driverPhone.replace("+", "").replace(" ", "").trim()
        val cleanDate = ride.pickupDate
        val cleanTime = ride.pickupTime

        val pickupCal = Calendar.getInstance().apply {
            time = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).apply { timeZone = TimeZone.getDefault() }.parse("${ride.pickupDate} ${ride.pickupTime}") ?: return
        }
        val pickupHour = pickupCal.get(Calendar.HOUR_OF_DAY)

        val isNightRide = pickupHour >= 22 || pickupHour < 6
        val status = ride.status.uppercase()

        // Ride_soon alarm for assigned/confirmed rides
        if (status in listOf("CONFIRMED", "ASSIGNED", "ACCEPTED")) {
            val reminderAt = pickupTs - 60 * 60 * 1000L
            if (reminderAt > now && bookingAge >= 30 * 60 * 1000L) {
                ReminderAlarmReceiver.scheduleAlarm(
                    ctx, ride.id, ride.bookingRef, "ride_soon",
                    customerPhone, ride.userEmail, ride.customerName,
                    driverPhone, ride.driverName,
                    ride.pickupLocation, ride.dropLocation, ride.cabType,
                    ride.totalFare.toInt().toString(),
                    cleanDate, cleanTime, reminderAt
                )
                Log.d(TAG, "Scheduled ride_soon alarm for #${ride.bookingRef} at ${((reminderAt - now) / 60000)}min from now")
            }
        }

        // Night ride alarm at 10PM for ALL rides (assigned or pending) with 10PM-6AM pickup
        if (isNightRide && status in listOf("CONFIRMED", "ASSIGNED", "ACCEPTED", "PENDING")) {
            val tenPmToday = try {
                val fmt = SimpleDateFormat("yyyy-MM-dd", Locale.US)
                val today = fmt.format(System.currentTimeMillis())
                val nightFmt = SimpleDateFormat("yyyy-MM-dd HH:mm", Locale.US).apply { timeZone = TimeZone.getDefault() }
                nightFmt.parse("$today 22:00")?.time ?: 0L
            } catch (e: Exception) { 0L }

            if (tenPmToday > now && tenPmToday < pickupTs) {
                ReminderAlarmReceiver.scheduleAlarm(
                    ctx, ride.id, ride.bookingRef, "night_ride",
                    customerPhone, ride.userEmail, ride.customerName,
                    driverPhone, ride.driverName,
                    ride.pickupLocation, ride.dropLocation, ride.cabType,
                    ride.totalFare.toInt().toString(),
                    cleanDate, cleanTime, tenPmToday
                )
                Log.d(TAG, "Scheduled night_ride alarm for #${ride.bookingRef} at 10PM today")
            }
        }

        // Unassigned alarms for PENDING rides
        if (status == "PENDING") {
            val bookingAgeMinutes = bookingAge / (60 * 1000L)

            val urgentAt = pickupTs - 90 * 60 * 1000L
            if (urgentAt > now && bookingAgeMinutes >= 30) {
                ReminderAlarmReceiver.scheduleAlarm(
                    ctx, ride.id, ride.bookingRef, "unassigned_urgent",
                    customerPhone, ride.userEmail, ride.customerName,
                    "", "",
                    ride.pickupLocation, ride.dropLocation, ride.cabType,
                    ride.totalFare.toInt().toString(),
                    cleanDate, cleanTime, urgentAt
                )
                Log.d(TAG, "Scheduled unassigned_urgent alarm for #${ride.bookingRef}")
            }

            val normalAt = pickupTs - 360 * 60 * 1000L
            if (normalAt > now && bookingAgeMinutes >= 30) {
                ReminderAlarmReceiver.scheduleAlarm(
                    ctx, ride.id, ride.bookingRef, "unassigned",
                    customerPhone, ride.userEmail, ride.customerName,
                    "", "",
                    ride.pickupLocation, ride.dropLocation, ride.cabType,
                    ride.totalFare.toInt().toString(),
                    cleanDate, cleanTime, normalAt
                )
                Log.d(TAG, "Scheduled unassigned alarm for #${ride.bookingRef}")
            }
        }
    }

    fun scheduleForRides(ctx: Context, rides: List<Booking>) {
        for (ride in rides) {
            scheduleForRide(ctx, ride)
        }
        Log.d(TAG, "Scheduled alarms for ${rides.size} rides")
    }
}
