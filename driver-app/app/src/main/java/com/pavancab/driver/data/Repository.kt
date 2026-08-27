package com.pavancab.driver.data

import android.content.Context
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter
import com.pavancab.driver.model.*
import com.pavancab.driver.network.ApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

private fun JsonObject?.safeInt(key: String, fallback: Int = 0): Int {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asInt
}

private fun JsonObject?.safeDouble(key: String, fallback: Double = 0.0): Double {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asDouble
}

class Repository(private val context: Context) {

    private val safeGson: Gson = GsonBuilder()
        .registerTypeAdapter(String::class.java, object : TypeAdapter<String>() {
            override fun read(`in`: JsonReader): String {
                return if (`in`.peek() == JsonToken.NULL) { `in`.nextNull(); "" } else `in`.nextString()
            }
            override fun write(out: JsonWriter, value: String?) { out.value(value ?: "") }
        })
        .create()

    suspend fun driverLogin(phone: String, password: String): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=login-with-password", mapOf(
            "phone" to phone, "password" to password
        ))
    }

    suspend fun getMyBookings(): List<Booking> {
        return try {
            val obj = ApiClient.rawGet("api/driver.php?action=my-bookings")
            val arr = if (obj.has("bookings")) obj.getAsJsonArray("bookings") else com.google.gson.JsonArray()
            arr.map { safeGson.fromJson(it, Booking::class.java) }
        } catch (e: Exception) { emptyList() }
    }

    suspend fun getBookingDetail(bookingId: Int): Booking? {
        return try {
            val obj = ApiClient.rawGet("api/driver.php?action=booking-detail&id=$bookingId")
            if (obj.has("booking")) safeGson.fromJson(obj.getAsJsonObject("booking"), Booking::class.java) else null
        } catch (e: Exception) { null }
    }

    suspend fun respondBooking(bookingId: Int, decision: String): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=respond", mapOf(
            "booking_id" to bookingId, "decision" to decision
        ))
    }

    suspend fun updateTripStatus(bookingId: Int, status: String): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=trip-status", mapOf(
            "booking_id" to bookingId, "status" to status
        ))
    }

    suspend fun ratePassenger(bookingId: Int, rating: Int, review: String = ""): JsonObject {
        val params = mutableMapOf<String, Any>("booking_id" to bookingId, "rating" to rating)
        if (review.isNotBlank()) params["review"] = review
        return ApiClient.rawPost("api/driver.php?action=rate-passenger", params)
    }

    suspend fun getEarnings(): JsonObject {
        return ApiClient.rawGet("api/driver.php?action=earnings")
    }

    suspend fun getProfile(): JsonObject {
        return ApiClient.rawGet("api/driver.php?action=profile")
    }

    suspend fun saveFcmTokenToServer(token: String) {
        try {
            val phone = UserPrefs.getPhone(context)
            ApiClient.rawPost("api/driver.php?action=save-fcm-token", mapOf(
                "fcm_token" to token, "phone" to phone
            ))
        } catch (_: Exception) {}
    }

    suspend fun logout(): JsonObject {
        return try {
            ApiClient.rawPost("api/driver.php?action=logout", emptyMap())
        } catch (e: Exception) { JsonObject() }
    }

    suspend fun checkSession(): JsonObject = ApiClient.rawGet("api/driver.php?action=check-session")

    suspend fun getSubscriptionStatus(): JsonObject {
        return ApiClient.rawGet("api/driver.php?action=subscription-status")
    }

    suspend fun cancelSubscription(): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=cancel-subscription", emptyMap())
    }

    suspend fun getQuickRides(): JsonObject {
        return ApiClient.rawGet("api/driver.php?action=quick-rides")
    }

    // Wallet snapshot filled by getQuickRidesFull() — read right after calling it
    var lastWalletBalance: Double = 0.0; private set
    var lastCanAcceptRides: Boolean = true; private set
    var lastIsPremium: Boolean = false; private set
    var lastCommissionPerRide: Double = 200.0; private set

    suspend fun getQuickRideList(): List<QuickRide> {
        return try {
            val obj = ApiClient.rawGet("api/driver.php?action=quick-rides")
            lastWalletBalance = try { obj.get("wallet_balance")?.asDouble ?: 0.0 } catch (_: Exception) { 0.0 }
            lastCanAcceptRides = try { obj.get("can_accept")?.asBoolean ?: true } catch (_: Exception) { true }
            lastIsPremium = try { obj.get("is_premium")?.asBoolean ?: false } catch (_: Exception) { false }
            lastCommissionPerRide = try { obj.get("commission_per_ride")?.asDouble ?: 200.0 } catch (_: Exception) { 200.0 }
            val arr = if (obj.has("rides")) obj.getAsJsonArray("rides") else return emptyList()
            arr.map { safeGson.fromJson(it, QuickRide::class.java) }
        } catch (e: Exception) { emptyList() }
    }

    suspend fun selfAssignRide(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=self-assign", mapOf("booking_id" to bookingId))
    }

    suspend fun declineRide(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=decline-ride", mapOf("booking_id" to bookingId))
    }

    suspend fun submitOffer(bookingId: Int, amount: Double, note: String = ""): JsonObject {
        val params = mutableMapOf<String, Any>("booking_id" to bookingId, "amount" to amount)
        if (note.isNotBlank()) params["note"] = note
        return ApiClient.rawPost("api/driver.php?action=submit-offer", params)
    }

    suspend fun createOrder(type: String, bookingId: Int = 0): JsonObject {
        val params = mutableMapOf("type" to type)
        if (bookingId > 0) params["booking_id"] = bookingId.toString()
        return ApiClient.rawPost("api/driver.php?action=create-order", params)
    }

    suspend fun verifyPayment(orderId: String, paymentId: String, type: String, bookingId: Int = 0): JsonObject {
        val params = mutableMapOf(
            "razorpay_order_id" to orderId,
            "razorpay_payment_id" to paymentId,
            "type" to type
        )
        if (bookingId > 0) params["booking_id"] = bookingId.toString()
        return ApiClient.rawPost("api/driver.php?action=verify-payment", params)
    }

    suspend fun getPaymentHistory(): JsonObject {
        return ApiClient.rawGet("api/driver.php?action=payment-history")
    }

    suspend fun getWallet(): JsonObject {
        return try { ApiClient.rawGet("api/driver.php?action=wallet") } catch (e: Exception) { JsonObject() }
    }

    suspend fun createWalletOrder(amount: Double): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=wallet-create-order", mapOf("amount" to amount))
    }

    suspend fun verifyWalletPayment(orderId: String, paymentId: String): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=wallet-verify", mapOf(
            "razorpay_order_id" to orderId, "razorpay_payment_id" to paymentId
        ))
    }

    suspend fun subscribeFromWallet(): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=subscribe-from-wallet", emptyMap())
    }

    suspend fun uploadAvatar(base64Image: String): JsonObject {
        return ApiClient.rawPost("api/driver.php?action=upload-avatar", mapOf("image" to base64Image))
    }

    suspend fun updateProfile(name: String, carModel: String, plateNumber: String): JsonObject {
        val map = mutableMapOf<String, Any>()
        if (name.isNotBlank()) map["name"] = name
        if (carModel.isNotBlank()) map["car_model"] = carModel
        if (plateNumber.isNotBlank()) map["plate_number"] = plateNumber
        return ApiClient.rawPost("api/driver.php?action=update-profile", map)
    }
}
