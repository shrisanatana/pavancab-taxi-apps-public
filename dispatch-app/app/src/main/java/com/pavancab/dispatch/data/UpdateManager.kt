package com.pavancab.dispatch.data

import android.content.Context
import com.google.gson.JsonObject
import com.pavancab.dispatch.BuildConfig
import com.pavancab.dispatch.network.ApiClient

data class UpdateInfo(
    val latestVersionCode: Int,
    val latestVersionName: String,
    val forceUpdate: Boolean,
    val message: String,
    val playStoreUrl: String
)

object UpdateManager {

    private const val PREFS = "pavancab_dispatch_update_prefs"

    fun rememberDismissed(context: Context, latestVersionCode: Int) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit().putInt("dismissed_$latestVersionCode", latestVersionCode).apply()
    }

    fun isDismissed(context: Context, latestVersionCode: Int): Boolean {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getInt("dismissed_$latestVersionCode", -1) == latestVersionCode
    }

    /** Fetches the update manifest and returns an UpdateInfo ONLY if a newer version exists. */
    suspend fun check(context: Context): UpdateInfo? {
        val pkg = BuildConfig.APPLICATION_ID
        val current = BuildConfig.VERSION_CODE
        return try {
            val root: JsonObject = ApiClient.rawGet("version.json")
            val cfg = runCatching { root.getAsJsonObject(pkg) }.getOrNull() ?: return null
            val latest = runCatching { cfg.get("latestVersionCode").asInt }.getOrNull() ?: return null
            if (latest <= current) return null
            val force = runCatching { cfg.get("forceUpdate").asBoolean }.getOrElse { false }
            val info = UpdateInfo(
                latestVersionCode = latest,
                latestVersionName = runCatching { cfg.get("latestVersionName").asString }.getOrElse { "" },
                forceUpdate = force,
                message = runCatching { cfg.get("message").asString }.getOrElse { "A new version of PAVANCAB Dispatch is available." },
                playStoreUrl = runCatching { cfg.get("playStoreUrl").asString }.getOrElse {
                    "https://play.google.com/store/apps/details?id=$pkg"
                }
            )
            if (!info.forceUpdate && isDismissed(context, latest)) null else info
        } catch (e: Exception) {
            null
        }
    }
}
