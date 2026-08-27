package com.pavancab.dispatch.data

import android.content.Context
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import com.google.gson.JsonNull
import com.google.gson.JsonObject
import com.google.gson.JsonParser
import com.google.gson.TypeAdapter
import com.google.gson.stream.JsonReader
import com.google.gson.stream.JsonToken
import com.google.gson.stream.JsonWriter
import com.pavancab.dispatch.model.*
import com.pavancab.dispatch.network.ApiClient
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

private fun JsonObject?.safeInt(key: String, fallback: Int): Int {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asInt
}

private fun JsonObject?.safeDouble(key: String, fallback: Double): Double {
    val v = this?.get(key) ?: return fallback
    return if (v is JsonNull) fallback else v.asDouble
}

class Repository(private val context: Context) {

    companion object {
        // Last API failure message â€” screens show this instead of fake "No results" states
        @Volatile var lastError: String? = null
    }

    private fun setErr(e: Exception) { lastError = e.message ?: "Network error" }
    private fun clearErr() { lastError = null }

    private val safeGson: Gson = GsonBuilder()
        .registerTypeAdapter(String::class.java, object : TypeAdapter<String>() {
            override fun read(`in`: JsonReader): String {
                return if (`in`.peek() == JsonToken.NULL) { `in`.nextNull(); "" } else `in`.nextString()
            }
            override fun write(out: JsonWriter, value: String?) { out.value(value ?: "") }
        })
        .registerTypeAdapter(Boolean::class.java, object : TypeAdapter<Boolean>() {
            override fun read(`in`: JsonReader): Boolean {
                return when (`in`.peek()) {
                    JsonToken.NULL -> { `in`.nextNull(); false }
                    JsonToken.BOOLEAN -> `in`.nextBoolean()
                    JsonToken.NUMBER -> `in`.nextInt() != 0
                    else -> { val s = `in`.nextString(); s.isNotEmpty() && s != "0" && !s.equals("false", true) }
                }
            }
            override fun write(out: JsonWriter, value: Boolean?) { out.value(value ?: false) }
        })
        .create()

    suspend fun checkSession(): JsonObject = ApiClient.rawGet("api/dispatch.php?action=me")

    suspend fun saveFcmTokenToServer(token: String) {
        try {
            val phone = UserPrefs.getPhone(context)
            val email = UserPrefs.getEmail(context)
            ApiClient.rawPost("api/dispatch.php?action=save_fcm_token", mapOf(
                "fcm_token" to token, "user_mobile" to phone, "user_email" to email
            ))
        } catch (_: Exception) {}
    }

    suspend fun logout(): JsonObject {
        try {
            val token = UserPrefs.getFcmToken(context)
            return ApiClient.rawPost("api/dispatch.php?action=logout", mapOf("fcm_token" to token))
        } catch (e: Exception) { return JsonObject() }
    }

    suspend fun getAllBookings(status: String = "", search: String = ""): List<Booking> {
        return try {
            val params = mutableListOf<String>()
            if (status.isNotBlank()) params.add("status=$status")
            if (search.isNotBlank()) params.add("search=${java.net.URLEncoder.encode(search, "UTF-8")}")
            val query = if (params.isNotEmpty()) "&" + params.joinToString("&") else ""
            val obj = ApiClient.rawGet("api/dispatch.php?action=all-bookings$query")
            val bookingsArr = if (obj.has("bookings")) obj.getAsJsonArray("bookings") else com.google.gson.JsonArray()
            bookingsArr.map { safeGson.fromJson(it, Booking::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun getAllBookingsPaged(
        status: String = "",
        search: String = "",
        startDate: String = "",
        endDate: String = "",
        page: Int = 1,
        limit: Int = 50,
        isFrozen: String = ""
    ): BookingsPage {
        return try {
            val params = mutableListOf("page=$page", "limit=$limit")
            if (status.isNotBlank()) params.add("status=$status")
            if (search.isNotBlank()) params.add("search=${java.net.URLEncoder.encode(search, "UTF-8")}")
            if (startDate.isNotBlank()) params.add("start_date=$startDate")
            if (endDate.isNotBlank()) params.add("end_date=$endDate")
            if (isFrozen.isNotBlank()) params.add("is_frozen=$isFrozen")
            val query = params.joinToString("&")
            val obj = ApiClient.rawGet("api/dispatch.php?action=all-bookings&$query")
            if (obj.has("error")) {
                val errMsg = obj.get("error")?.asString ?: "Server error"
                if (errMsg.contains("auth", true) || errMsg.contains("login", true)) {
                    lastError = "SESSION_EXPIRED"
                } else {
                    lastError = errMsg
                }
                return BookingsPage(emptyList(), 0, 0)
            }
            lastError = null
            val bookingsArr = if (obj.has("bookings")) obj.getAsJsonArray("bookings") else com.google.gson.JsonArray()
            val bookings = bookingsArr.map { safeGson.fromJson(it, Booking::class.java) }
            val total = obj.safeInt("total", 0)
            val pages = obj.safeInt("pages", 0)
            BookingsPage(bookings, total, pages)
        } catch (e: Exception) {
            lastError = when (e) {
                is com.pavancab.dispatch.network.ApiException -> if (e.code == 401 || e.code == 403) "SESSION_EXPIRED" else e.message ?: "Error"
                else -> "Connection error"
            }
            BookingsPage(emptyList(), 0, 0)
        }
    }

    suspend fun getCommissionReport(days: Int = 30): CommissionReport {
        return try {
            val obj = ApiClient.rawGet("api/dispatch.php?action=commission-report&days=$days")
            val dailyArr = if (obj.has("daily")) obj.getAsJsonArray("daily") else com.google.gson.JsonArray()
            val daily = dailyArr.map { safeGson.fromJson(it, CommissionDay::class.java) }
            val totalCommission = obj.safeDouble("total_commission", 0.0)
            val totalRides = obj.safeInt("total_rides", 0)
            val commissionPerRide = obj.safeInt("commission_per_ride", 300)
            CommissionReport(daily, totalCommission, totalRides, commissionPerRide)
        } catch (e: Exception) { setErr(e); CommissionReport(emptyList(), 0.0, 0, 300) }
    }

    suspend fun getUpcomingRidesNeedingReminders(): List<Booking> {
        return try {
            val obj = ApiClient.rawGet("api/dispatch.php?action=upcoming-rides")
            val arr = if (obj.has("rides")) obj.getAsJsonArray("rides") else com.google.gson.JsonArray()
            arr.map { safeGson.fromJson(it, Booking::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun markReminderSent(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=mark-reminder-sent", mapOf("booking_id" to bookingId))
    }

    suspend fun checkTeamAccess(): JsonObject {
        val phone = UserPrefs.getPhone(context)
        val email = UserPrefs.getEmail(context)
        return ApiClient.rawGet("api/dispatch.php?action=check-access&phone=$phone&email=$email")
    }

    suspend fun getBookingDetail(bookingId: Int): Booking? {
        return try {
            val obj = ApiClient.rawGet("api/dispatch.php?action=booking-detail&id=$bookingId")
            if (obj.has("booking")) {
                clearErr()
                safeGson.fromJson(obj.getAsJsonObject("booking"), Booking::class.java)
            } else {
                val errEl = obj.get("error")
                lastError = "Server: " + ((if (errEl is JsonNull) null else errEl?.asString) ?: "No booking data")
                null
            }
        } catch (e: Exception) {
            setErr(e)
            null
        }
    }

    suspend fun assignDriver(bookingId: Int, driverId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=assign-driver", mapOf(
            "booking_id" to bookingId, "driver_id" to driverId
        ))
    }

    suspend fun updateBookingStatus(bookingId: Int, status: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=update-status", mapOf(
            "booking_id" to bookingId, "status" to status
        ))
    }

    suspend fun cancelBooking(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=cancel-ride", mapOf("booking_id" to bookingId))
    }

    suspend fun boostFare(bookingId: Int, amount: Double): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=boost-fare", mapOf(
            "booking_id" to bookingId, "boost_amount" to amount
        ))
    }

    suspend fun freezeRide(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=freeze-ride", mapOf("booking_id" to bookingId))
    }

    suspend fun unfreezeRide(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=unfreeze-ride", mapOf("booking_id" to bookingId))
    }

    suspend fun adjustFare(bookingId: Int, newFare: Double): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=edit-fare", mapOf(
            "booking_id" to bookingId, "new_fare" to newFare
        ))
    }

    suspend fun getDrivers(status: String = ""): List<Driver> {
        clearErr()
        return try {
            val path = if (status.isNotBlank()) "api/dispatch.php?action=drivers&status=$status" else "api/dispatch.php?action=drivers"
            val raw = ApiClient.rawGetArray(path)
            val arr = extractArray(raw, "drivers") ?: return emptyList()
            arr.map { safeGson.fromJson(it, Driver::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun addDriver(name: String, phone: String, carModel: String, plateNumber: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=add-driver", mapOf(
            "name" to name, "phone" to phone, "car_model" to carModel, "plate_number" to plateNumber
        ))
    }

    suspend fun toggleDriverStatus(driverId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=toggle-driver-status", mapOf("driver_id" to driverId))
    }

    suspend fun deleteDriver(driverId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=delete-driver", mapOf("driver_id" to driverId))
    }

    suspend fun editDriver(driverId: Int, name: String, phone: String, carModel: String, plateNumber: String): JsonObject {
        val map = mutableMapOf<String, Any>("driver_id" to driverId)
        if (name.isNotBlank()) map["name"] = name
        if (phone.isNotBlank()) map["phone"] = phone
        if (carModel.isNotBlank()) map["car_model"] = carModel
        if (plateNumber.isNotBlank()) map["plate_number"] = plateNumber
        return ApiClient.rawPost("api/dispatch.php?action=edit-driver", map)
    }

    private fun extractArray(raw: String, vararg keys: String): com.google.gson.JsonArray? {
        return try {
            val el = JsonParser.parseString(raw)
            when {
                el.isJsonArray -> el.asJsonArray
                el.isJsonObject -> keys.firstNotNullOfOrNull { k ->
                    (el.asJsonObject.get(k) as? com.google.gson.JsonArray)
                }
                else -> null
            }
        } catch (e: Exception) { null }
    }

    suspend fun getTeamMembers(): List<TeamMember> {
        return try {
            val raw = ApiClient.rawGetArray("api/dispatch.php?action=team")
            val arr = extractArray(raw, "members") ?: return emptyList()
            arr.map { safeGson.fromJson(it, TeamMember::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun addTeamMember(name: String, phone: String, email: String, role: String = "team"): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=add-team", mapOf(
            "member_name" to name, "member_phone" to phone, "member_email" to email, "role" to role
        ))
    }

    suspend fun removeTeamMember(memberId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=remove-team", mapOf("member_id" to memberId))
    }

    suspend fun getStats(): JsonObject {
        return try {
            ApiClient.rawGet("api/dispatch.php?action=stats")
        } catch (e: Exception) { JsonObject() }
    }

    suspend fun createBooking(
        name: String, phone: String, email: String, tripType: String,
        pickup: String, drop: String, date: String, time: String,
        cabType: String, fare: Double, notes: String = ""
    ): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=create-booking", mapOf(
            "customer_name" to name, "customer_phone" to phone, "user_email" to email,
            "trip_type" to tripType, "pickup_location" to pickup, "drop_location" to drop,
            "pickup_date" to date, "pickup_time" to time, "cab_type" to cabType,
            "total_fare" to fare, "special_notes" to notes
        ))
    }

    suspend fun sendPush(token: String, title: String, body: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=send-push", mapOf(
            "fcm_token" to token, "title" to title, "body" to body
        ))
    }

    suspend fun broadcastPush(title: String, body: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=broadcast-push", mapOf(
            "title" to title, "body" to body
        ))
    }

    suspend fun proposeFare(bookingId: Int, proposedFare: Double, reason: String = "Driver asking minimum fare"): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=propose-fare", mapOf(
            "booking_id" to bookingId, "proposed_fare" to proposedFare, "reason" to reason
        ))
    }

    suspend fun getActiveUsers(startDate: String = "", endDate: String = ""): List<ActiveUser> {
        return try {
            val params = mutableListOf<String>()
            if (startDate.isNotBlank()) params.add("start_date=$startDate")
            if (endDate.isNotBlank()) params.add("end_date=$endDate")
            val query = if (params.isNotEmpty()) "&" + params.joinToString("&") else ""
            val raw = ApiClient.rawGetArray("api/dispatch.php?action=users$query")
            val arr = JsonParser.parseString(raw).asJsonArray
            arr.map { safeGson.fromJson(it, ActiveUser::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun sendPersonalPush(userPhone: String, userEmail: String, title: String, body: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=send-personal-push", mapOf(
            "user_phone" to userPhone, "user_email" to userEmail, "title" to title, "body" to body
        ))
    }

    suspend fun createPhoneBooking(
        name: String, phone: String, tripType: String,
        pickup: String, drop: String, date: String, time: String,
        cabType: String, fare: Double, notes: String = ""
    ): JsonObject {
        val bookedByPhone = UserPrefs.getPhone(context)
        val bookedByName = UserPrefs.getName(context)
        return ApiClient.rawPost("api/dispatch.php?action=create-booking", mapOf(
            "customer_name" to name, "customer_phone" to phone,
            "trip_type" to tripType, "pickup_location" to pickup, "drop_location" to drop,
            "pickup_date" to date, "pickup_time" to time, "cab_type" to cabType,
            "total_fare" to fare, "special_notes" to notes,
            "booked_by_phone" to bookedByPhone, "booked_by_name" to bookedByName
        ))
    }

    suspend fun getBulkFcmTokens(phones: List<String>, emails: List<String>): List<String> {
        return try {
            val obj = ApiClient.rawPost("api/dispatch.php?action=bulk-tokens", mapOf(
                "phones" to phones, "emails" to emails
            ))
            val arr = obj.getAsJsonArray("tokens") ?: return emptyList()
            arr.filter { it !is JsonNull }.map { it.asString }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun bulkPush(tokens: List<String>, title: String, body: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=bulk-push", mapOf(
            "tokens" to tokens, "title" to title, "body" to body
        ))
    }

    suspend fun bulkWhatsApp(phones: List<String>, message: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=bulk-whatsapp", mapOf(
            "phones" to phones, "message" to message
        ))
    }

    suspend fun getWhatsAppConfig(): JsonObject {
        return ApiClient.rawGet("api/dispatch.php?action=whatsapp-config")
    }

    suspend fun saveWhatsAppConfig(token: String, phoneId: String): JsonObject {
        val body = mutableMapOf<String, Any>()
        if (token.isNotBlank()) body["token"] = token
        if (phoneId.isNotBlank()) body["phone_id"] = phoneId
        return ApiClient.rawPost("api/dispatch.php?action=update-whatsapp-config", body)
    }

    suspend fun deleteBooking(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=delete-booking", mapOf("booking_id" to bookingId))
    }

    suspend fun banUser(userId: Int, ban: Boolean): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=ban-user", mapOf("user_id" to userId, "ban" to if (ban) 1 else 0))
    }

    suspend fun deleteUser(userId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=delete-user", mapOf("user_id" to userId))
    }

    suspend fun editBooking(bookingId: Int, name: String, phone: String, pickup: String, drop: String, date: String, time: String, cabType: String, fare: Double, notes: String): JsonObject {
        val map = mutableMapOf<String, Any>("booking_id" to bookingId)
        if (name.isNotBlank()) map["customer_name"] = name
        if (phone.isNotBlank()) map["customer_phone"] = phone
        if (pickup.isNotBlank()) map["pickup_location"] = pickup
        if (drop.isNotBlank()) map["drop_location"] = drop
        if (date.isNotBlank()) map["pickup_date"] = date
        if (time.isNotBlank()) map["pickup_time"] = time
        if (cabType.isNotBlank()) map["cab_type"] = cabType
        if (fare > 0) map["total_fare"] = fare
        if (notes.isNotBlank()) map["special_notes"] = notes
        return ApiClient.rawPost("api/dispatch.php?action=edit-booking", map)
    }

    suspend fun getUserDetail(phone: String = "", email: String = "", userId: Int = 0): JsonObject {
        val params = mutableListOf<String>()
        if (phone.isNotBlank()) params.add("phone=$phone")
        if (email.isNotBlank()) params.add("email=$email")
        if (userId > 0) params.add("id=$userId")
        return ApiClient.rawGet("api/dispatch.php?action=user-detail&${params.joinToString("&")}")
    }

    suspend fun getDriverDetail(driverId: Int): JsonObject {
        clearErr()
        return try { ApiClient.rawGet("api/dispatch.php?action=driver-detail&driver_id=$driverId") } catch (e: Exception) { setErr(e); JsonObject() }
    }

    suspend fun markCommissionPaid(bookingId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=mark-commission-paid", mapOf("booking_id" to bookingId))
    }

    suspend fun approveDriver(driverId: Int, approve: Boolean = true): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=approve-driver", mapOf("driver_id" to driverId, "approve" to if (approve) 1 else 0))
    }

    suspend fun getRideReports(): List<RideReport> {
        return try {
            val arr = extractArray(ApiClient.rawGetArray("api/dispatch.php?action=ride-reports"), "data", "reports") ?: return emptyList()
            arr.map { safeGson.fromJson(it, RideReport::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun updateReportStatus(reportId: Int, status: String, response: String = ""): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=update-report", mapOf(
            "report_id" to reportId, "status" to status, "admin_response" to response
        ))
    }

    suspend fun toggleTeamMember(memberId: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=toggle-team", mapOf("member_id" to memberId))
    }

    suspend fun updateTeamRole(memberId: Int, role: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=update-team-role", mapOf("member_id" to memberId, "role" to role))
    }

    suspend fun updateProfile(name: String, email: String): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=profile-update", mapOf("name" to name, "email" to email))
    }

    suspend fun getCommissionConfig(): JsonObject {
        return ApiClient.rawGet("api/dispatch.php?action=commission-config")
    }

    suspend fun saveCommissionConfig(rate: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=update-commission-config", mapOf("commission_per_ride" to rate))
    }

    suspend fun respondFare(bookingId: Int, response: String, newFare: Double = 0.0): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=respond-fare", mapOf(
            "booking_id" to bookingId, "response" to response, "new_fare" to newFare
        ))
    }

    suspend fun getDriverConfig(): JsonObject {
        return ApiClient.rawGet("api/dispatch.php?action=driver-config")
    }

    suspend fun saveDriverConfig(subscriptionAmount: Int, commissionPerRide: Int): JsonObject {
        return ApiClient.rawPost("api/dispatch.php?action=update-driver-config", mapOf(
            "driver_subscription_amount" to subscriptionAmount,
            "driver_commission_per_ride" to commissionPerRide
        ))
    }
}
