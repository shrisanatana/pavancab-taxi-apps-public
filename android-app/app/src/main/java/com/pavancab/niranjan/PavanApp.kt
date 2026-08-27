package com.pavancab.niranjan

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import com.pavancab.niranjan.network.ApiClient

class PavanApp : Application() {
    override fun onCreate() {
        super.onCreate()
        ApiClient.appContext = this
        CrashLogger.installGlobalHandler(this)
        try { createNotificationChannels() } catch (_: Exception) {}
    }

    private fun createNotificationChannels() {
        val nm = getSystemService(NotificationManager::class.java)

        val defaultChannel = NotificationChannel("default", "General Notifications", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "All PAVANCAB notifications"
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 300, 200, 300)
        }
        nm.createNotificationChannel(defaultChannel)

        val rideChannel = NotificationChannel("pavancab_rides", "Ride Updates", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "PAVANCAB ride notifications"
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 300, 200, 300)
        }
        nm.createNotificationChannel(rideChannel)

        val dispatchChannel = NotificationChannel("pavancab_dispatch", "PavanCab Ride Updates", NotificationManager.IMPORTANCE_HIGH).apply {
            description = "Ride notifications"
            enableVibration(true)
            vibrationPattern = longArrayOf(0, 300, 200, 300)
        }
        nm.createNotificationChannel(dispatchChannel)
    }
}
