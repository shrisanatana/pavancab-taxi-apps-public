package com.pavancab.dispatch.network

import android.content.Context
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.JsonParser
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.*
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import java.util.concurrent.TimeUnit

class ApiException(val code: Int, message: String) : Exception(message)

object ApiClient {
    private const val BASE_URL = "https://pavancab.com/app/"
    var appContext: Context? = null

    @Volatile
    var sessionExpired: Boolean = false
        private set

    private fun detectAuthIssue(httpCode: Int, body: String) {
        if (sessionExpired) return
        if (httpCode == 401 || httpCode == 403) { sessionExpired = true; return }
        if (body.contains("\"isLoggedIn\":false") ||
            body.contains("Login required") ||
            body.contains("Not logged in") ||
            body.contains("Authentication required")) {
            sessionExpired = true
        }
    }

    fun clearSessionExpiredFlag() { sessionExpired = false }

    fun checkNetwork(): String? {
        val ctx = appContext ?: return "No app context"
        val cm = ctx.getSystemService(Context.CONNECTIVITY_SERVICE) as android.net.ConnectivityManager
        val net = cm.activeNetwork ?: return "No internet connection"
        val caps = cm.getNetworkCapabilities(net) ?: return "No internet connection"
        if (!caps.hasCapability(android.net.NetworkCapabilities.NET_CAPABILITY_INTERNET)) return "No internet connection"
        return null
    }

    fun humanError(e: Throwable): String {
        val netErr = checkNetwork()
        if (netErr != null) return netErr
        return when (e) {
            is ApiException -> when (e.code) {
                401, 403 -> "Session expired. Please login again."
                500 -> "Server error. Try again."
                else -> e.message ?: "Error"
            }
            is SocketTimeoutException -> "Server timed out. Try again."
            is UnknownHostException -> "Cannot reach server. Check internet."
            else -> e.message?.takeIf { it.isNotBlank() } ?: "Connection error"
        }
    }

    val cookieJar = SessionCookieJar()

    val client = OkHttpClient.Builder()
        .cookieJar(cookieJar)
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .writeTimeout(15, TimeUnit.SECONDS)
        .build()

    private val retrofit = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .client(client)
        .addConverterFactory(GsonConverterFactory.create())
        .build()

    val api: DispatchApi = retrofit.create(DispatchApi::class.java)

    suspend fun rawPost(path: String, body: Map<String, Any>): JsonObject = withContext(Dispatchers.IO) {
        val formBody = body.entries.joinToString("&") { "${java.net.URLEncoder.encode(it.key, "UTF-8")}=${java.net.URLEncoder.encode(it.value.toString(), "UTF-8")}" }
        val requestBody = formBody.toRequestBody("application/x-www-form-urlencoded; charset=utf-8".toMediaType())
        val request = Request.Builder()
            .url(BASE_URL + path)
            .post(requestBody)
            .addHeader("Content-Type", "application/x-www-form-urlencoded")
            .addHeader("Accept", "application/json")
            .build()
        val response = client.newCall(request).execute()
        val responseBody = response.body?.string() ?: "{}"
        detectAuthIssue(response.code, responseBody)
        if (response.code == 401 || response.code == 403) {
            throw ApiException(response.code, "Session expired. Please login again.")
        }
        try { JsonParser.parseString(responseBody).asJsonObject } catch (e: Exception) { JsonObject() }
    }

    suspend fun rawGet(path: String): JsonObject = withContext(Dispatchers.IO) {
        val request = Request.Builder()
            .url(BASE_URL + path)
            .addHeader("Accept", "application/json")
            .build()
        val response = client.newCall(request).execute()
        val responseBody = response.body?.string() ?: "{}"
        detectAuthIssue(response.code, responseBody)
        if (response.code == 401 || response.code == 403) {
            throw ApiException(response.code, "Session expired. Please login again.")
        }
        try { JsonParser.parseString(responseBody).asJsonObject } catch (e: Exception) { JsonObject() }
    }

    suspend fun rawGetArray(path: String): String = withContext(Dispatchers.IO) {
        val request = Request.Builder()
            .url(BASE_URL + path)
            .addHeader("Accept", "application/json")
            .build()
        val response = client.newCall(request).execute()
        val responseBody = response.body?.string() ?: "[]"
        detectAuthIssue(response.code, responseBody)
        if (response.code == 401 || response.code == 403) {
            throw ApiException(response.code, "Session expired. Please login again.")
        }
        responseBody
    }
}

interface DispatchApi {
    @FormUrlEncoded
    @POST("api/dispatch.php")
    suspend fun sendOtp(@Field("phone") phone: String, @Field("app_type") appType: String = "dispatch"): JsonObject

    @FormUrlEncoded
    @POST("api/dispatch.php")
    suspend fun verifyOtp(
        @Field("phone") phone: String,
        @Field("otp") otp: String,
        @Field("name") name: String = "",
        @Field("email") email: String = "",
        @Field("fcm_token") fcmToken: String = ""
    ): JsonObject

    @GET("api/dispatch.php?action=me")
    suspend fun checkSession(): JsonObject

    @POST("api/dispatch.php?action=logout")
    suspend fun logout(@Field("fcm_token") fcmToken: String = ""): JsonObject
}
