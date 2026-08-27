package com.pavancab.niranjan.network

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.JsonParser
import com.pavancab.niranjan.model.*
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

object ApiClient {
    private const val BASE_URL = "https://pavancab.com/app/"
    var appContext: Context? = null

    /** Set when server says session invalid — MainActivity watches this and returns user to login */
    @Volatile
    var sessionExpired: Boolean = false
    private set

    private fun detectAuthIssue(httpCode: Int, body: String) {
        if (sessionExpired) return
        if (httpCode == 401) { sessionExpired = true; return }
        // Backend sometimes returns 200 with auth-failure JSON
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
        val cm = ctx.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        val net = cm.activeNetwork ?: return "No internet connection"
        val caps = cm.getNetworkCapabilities(net) ?: return "No internet connection"
        if (!caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)) return "No internet connection"
        return null
    }

    fun humanError(e: Throwable): String {
        val netErr = checkNetwork()
        if (netErr != null) return netErr
        return when (e) {
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

    val api: PavanApi = retrofit.create(PavanApi::class.java)

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
        val code = response.code
        val responseBody = response.body?.string() ?: "{}"
        detectAuthIssue(code, responseBody)
        try { JsonParser.parseString(responseBody).asJsonObject } catch (e: Exception) {
            android.util.Log.e("PAVANCAB", "rawPost parse error: ${e.message}")
            if (code >= 400) JsonObject().apply { addProperty("error", "Server error (HTTP $code)") }
            else JsonObject().apply { addProperty("success", false); addProperty("error", "Invalid server response") }
        }
    }

    suspend fun rawGet(path: String): JsonObject = withContext(Dispatchers.IO) {
        val request = Request.Builder()
            .url(BASE_URL + path)
            .addHeader("Accept", "application/json")
            .build()
        val response = client.newCall(request).execute()
        val responseBody = response.body?.string() ?: "{}"
        detectAuthIssue(response.code, responseBody)
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
        responseBody
    }
}

interface PavanApi {
    @FormUrlEncoded
    @POST("api/passenger.php")
    suspend fun sendOtp(@Field("phone") phone: String): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php")
    suspend fun verifyOtp(
        @Field("phone") phone: String,
        @Field("otp") otp: String,
        @Field("name") name: String = "",
        @Field("email") email: String = "",
        @Field("fcm_token") fcmToken: String = ""
    ): JsonObject

    @GET("api/passenger.php?action=me")
    suspend fun checkSession(): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php?action=logout")
    suspend fun logout(@Field("fcm_token") fcmToken: String = ""): JsonObject

    @GET("api/passenger.php?action=pickups")
    suspend fun getPickups(): List<PickupPlace>

    @GET("api/passenger.php?action=drops")
    suspend fun getDrops(@Query("pickup_id") pickupId: Int): List<DropFare>

    @GET("api/passenger.php?action=hourly")
    suspend fun getHourlyFares(@Query("place_id") placeId: Int): List<HourlyFare>

    @GET("api/passenger.php?action=tours")
    suspend fun getTours(@Query("place_id") placeId: Int): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php?action=create-booking")
    suspend fun createBooking(
        @Field("customer_name") name: String,
        @Field("customer_phone") phone: String,
        @Field("user_email") email: String,
        @Field("trip_type") tripType: String,
        @Field("pickup_location") pickup: String,
        @Field("drop_location") drop: String,
        @Field("pickup_date") date: String,
        @Field("pickup_time") time: String,
        @Field("cab_type") cabType: String,
        @Field("total_fare") fare: Double,
        @Field("special_notes") notes: String = "",
        @Field("fcm_token") fcmToken: String = ""
    ): JsonObject

    @GET("api/passenger.php?action=my-bookings")
    suspend fun getBookings(
        @Query("phone") phone: String,
        @Query("email") email: String = ""
    ): List<Booking>

    @FormUrlEncoded
    @POST("api/passenger.php?action=cancel-booking")
    suspend fun cancelBooking(@Field("booking_id") bookingId: Int): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php?action=boost-fare")
    suspend fun boostFare(
        @Field("booking_id") bookingId: Int,
        @Field("boost_amount") amount: Double
    ): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php?action=rate-ride")
    suspend fun rateRide(
        @Field("booking_id") bookingId: Int,
        @Field("rating") rating: Int,
        @Field("review_text") review: String = ""
    ): JsonObject

    @FormUrlEncoded
    @POST("api/passenger.php")
    suspend fun saveFcmToken(
        @Field("action") action: String = "save_fcm_token",
        @Field("fcm_token") fcmToken: String,
        @Field("user_mobile") mobile: String = "",
        @Field("user_email") email: String = ""
    ): JsonObject
}
