package com.pavancab.dispatch

import android.content.Context
import android.os.Build
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.PrintWriter
import java.io.StringWriter
import java.util.concurrent.TimeUnit

class CrashLogger(private val context: Context) : Thread.UncaughtExceptionHandler {
    private val defaultHandler = Thread.getDefaultUncaughtExceptionHandler()

    override fun uncaughtException(thread: Thread, ex: Throwable) {
        try {
            val sw = StringWriter()
            ex.printStackTrace(PrintWriter(sw))
            log("CRASH", sw.toString().take(2000), "UncaughtException")
        } catch (_: Exception) {}
        defaultHandler?.uncaughtException(thread, ex)
    }

    companion object {
        fun log(type: String, message: String, screen: String, ex: Throwable? = null) {
            Thread {
                try {
                    val json = """{"log_type":"$type","message":"${message.take(1000).replace("\"", "'")}","screen":"$screen","device_info":"${Build.MANUFACTURER} ${Build.MODEL} Android ${Build.VERSION.RELEASE}"}"""
                    val body = json.toRequestBody("application/json".toMediaType())
                    val req = Request.Builder().url("https://pavancab.com/app/crash_log.php").post(body).build()
                    OkHttpClient.Builder().connectTimeout(5, TimeUnit.SECONDS).build().newCall(req).execute()
                } catch (_: Exception) {}
            }.start()
        }
    }
}
