package com.pavancab.dispatch.worker

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log
import com.pavancab.dispatch.data.Repository
import com.pavancab.dispatch.data.UserPrefs
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED && intent.action != Intent.ACTION_MY_PACKAGE_REPLACED) return

        Log.d("BootReceiver", "Boot/package replaced — rescheduling alarms")

        val pendingResult = goAsync()

        CoroutineScope(Dispatchers.IO).launch {
            try {
                val isAdmin = UserPrefs.isAdmin(ctx)
                if (!isAdmin) {
                    Log.d("BootReceiver", "Not admin, skipping")
                    return@launch
                }

                val repo = Repository(ctx)
                val rides = repo.getUpcomingRidesNeedingReminders()
                AlarmScheduler.scheduleForRides(ctx, rides)
                Log.d("BootReceiver", "Rescheduled alarms for ${rides.size} rides")
            } catch (e: Exception) {
                Log.e("BootReceiver", "Failed: ${e.message}")
            } finally {
                pendingResult.finish()
            }
        }
    }
}
