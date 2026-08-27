package com.pavancab.niranjan

import android.content.Context
import android.os.Build
import android.os.Handler
import android.os.Looper
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.PrintWriter
import java.io.StringWriter
import java.net.URLEncoder

object CrashLogger {
    private const val URL = "https://pavancab.com/app/crash_log.php"
    private var appVersion = "1.1"

    fun init(context: Context) {
        try {
            val pInfo = context.packageManager.getPackageInfo(context.packageName, 0)
            appVersion = pInfo.versionName ?: "1.1"
        } catch (_: Exception) {}
    }

    fun log(type: String, message: String, screen: String = "", throwable: Throwable? = null) {
        try {
            val sw = StringWriter()
            throwable?.printStackTrace(PrintWriter(sw))
            val stackTrace = sw.toString()
            val device = "${Build.MANUFACTURER} ${Build.MODEL} Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})"

            val json = """{"type":"$type","message":"${escapeJson(message)}","stacktrace":"${escapeJson(stackTrace)}","device_info":"$device","app_version":"$appVersion","screen":"$screen"}"""

            val body = json.toRequestBody("application/json; charset=utf-8".toMediaType())
            val request = Request.Builder().url(URL).post(body).build()
            OkHttpClient.Builder().build().newCall(request).enqueue(object : Callback {
                override fun onFailure(call: Call, e: java.io.IOException) {}
                override fun onResponse(call: Call, response: Response) { response.close() }
            })
        } catch (_: Exception) {}
    }

    fun installGlobalHandler(context: Context) {
        init(context)
        val defaultHandler = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                log("CRASH", "${throwable.javaClass.simpleName}: ${throwable.message}", "global", throwable)
                Thread.sleep(1500)
            } catch (_: Exception) {}
            defaultHandler?.uncaughtException(thread, throwable)
        }
    }

    private fun escapeJson(s: String): String {
        return s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n").replace("\r", "\\r").replace("\t", "\\t").take(4000)
    }
}
