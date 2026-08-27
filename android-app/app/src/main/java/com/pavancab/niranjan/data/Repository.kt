package com.pavancab.niranjan.data

import android.content.Context
import com.google.gson.Gson
import com.google.gson.JsonObject
import com.google.gson.JsonParser
import com.google.gson.reflect.TypeToken
import com.pavancab.niranjan.model.*
import com.pavancab.niranjan.network.ApiClient

class Repository(private val context: Context) {

    companion object {
        /** Last API failure — screens show this with RETRY instead of silent empties */
        @Volatile var lastError: String? = null
    }

    private fun setErr(e: Exception) { lastError = e.message ?: "Network error" }
    private fun clearErr() { lastError = null }
    private val api get() = ApiClient.api

    suspend fun sendOtp(phone: String, appType: String = "passenger"): JsonObject {
        return ApiClient.rawPost("api/passenger.php?action=send_otp", mapOf("phone" to phone, "app_type" to appType))
    }

    suspend fun verifyOtp(phone: String, otp: String, name: String = "", email: String = ""): JsonObject {
        val fcm = UserPrefs.getFcmToken(context)
        return ApiClient.rawPost("api/passenger.php?action=verify_otp", mapOf(
            "phone" to phone, "otp" to otp, "name" to name,
            "email" to email, "fcm_token" to fcm
        ))
    }

    suspend fun checkSession(): JsonObject {
        return ApiClient.rawGet("api/passenger.php?action=me")
    }

    /** Silent re-login using persistent remember-token (survives server session death) */
    suspend fun autoLogin(context: Context): JsonObject {
        return try {
            val token = UserPrefs.getAutoToken(context)
            if (token.isBlank()) return JsonObject()
            ApiClient.rawPost("api/passenger.php?action=auto_login", mapOf("remember_token" to token))
        } catch (e: Exception) { setErr(e); JsonObject() }
    }

    suspend fun logout(): JsonObject {
        val fcm = UserPrefs.getFcmToken(context)
        val r = try { api.logout(fcm) } catch (e: Exception) { JsonObject() }
        UserPrefs.clearUser(context)
        ApiClient.cookieJar.clear()
        return r
    }

    suspend fun getPickups(type: String = "all"): List<PickupPlace> {
        clearErr()
        val obj = ApiClient.rawGet("api/passenger.php?action=pickups&type=$type")
        return try {
            val arr = obj.getAsJsonArray("pickups") ?: obj.getAsJsonArray("data") ?: return emptyList()
            arr.map { Gson().fromJson(it, PickupPlace::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun getDrops(pickupId: Int): List<DropFare> {
        clearErr()
        val obj = ApiClient.rawGet("api/passenger.php?action=drops&pickup_id=$pickupId")
        return try {
            val arr = obj.getAsJsonArray("drops") ?: obj.getAsJsonArray("data") ?: return emptyList()
            arr.map { Gson().fromJson(it, DropFare::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun getHourlyFares(placeId: Int): List<HourlyFare> {
        val (map, _) = getHourlyFaresMap(placeId)
        val result = mutableListOf<HourlyFare>()
        map.forEach { (cabType, durations) ->
            durations.forEach { (dur, price) ->
                result.add(HourlyFare(cabType = cabType, hours = dur, price = price, placeId = placeId))
            }
        }
        return result
    }

    suspend fun getHourlyFaresMap(placeId: Int): Pair<Map<String, Map<Int, Double>>, HourlyExtra> {
        val obj = ApiClient.rawGet("api/passenger.php?action=hourly&place_id=$placeId")
        return try {
            val faresObj = obj.getAsJsonObject("fares") ?: return Pair(emptyMap(), HourlyExtra())
            val result = mutableMapOf<String, Map<Int, Double>>()
            faresObj.entrySet().forEach { (cabType, durations) ->
                val durMap = mutableMapOf<Int, Double>()
                durations.asJsonObject.entrySet().forEach { (dur, price) ->
                    durMap[dur.toInt()] = price.asDouble
                }
                result[cabType] = durMap
            }
            val extraObj = obj.getAsJsonObject("extra")
            val extra = if (extraObj != null) {
                HourlyExtra(
                    kmRate = extraObj.get("km_rate")?.asDouble ?: 25.0,
                    hrRate = extraObj.get("hr_rate")?.asDouble ?: 200.0,
                    nightRate = extraObj.get("night_rate")?.asDouble ?: 500.0
                )
            } else HourlyExtra()
            Pair(result, extra)
        } catch (e: Exception) { setErr(e); Pair(emptyMap(), HourlyExtra()) }
    }

    suspend fun getTours(placeId: Int): List<Tour> {
        val obj = ApiClient.rawGet("api/passenger.php?action=tours&place_id=$placeId")
        return try {
            val arr = obj.getAsJsonArray("tours") ?: obj.getAsJsonArray("data") ?: return emptyList()
            arr.map { Gson().fromJson(it, Tour::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun getBookings(phone: String, email: String = ""): List<Booking> {
        val path = if (email.isNotEmpty()) "api/passenger.php?action=my-bookings&phone=$phone&email=$email" else "api/passenger.php?action=my-bookings&phone=$phone"
        clearErr()
        val raw = try {
            ApiClient.rawGetArray(path)
        } catch (e: Exception) {
            setErr(e); return cachedBookings()
        }
        // If session expired, don't fake "offline" — let session watcher fix it, next poll will succeed
        if (ApiClient.sessionExpired) {
            return emptyList()
        }
        // Parse fresh
        val parsed = parseBookings(raw)
        if (parsed.isNotEmpty()) {
            UserPrefs.saveCache(context, "bookings_$phone", raw)
            return parsed
        }
        // Server responded with success but empty bookings — valid
        if (raw.contains("\"success\":true")) {
            UserPrefs.saveCache(context, "bookings_$phone", raw)
            return parsed
        }
        // Server returned error/HTML/unparseable — fall back to cache
        val cached = cachedBookings()
        if (cached.isNotEmpty()) { lastError = "OFFLINE"; return cached }
        return parsed
    }

    private suspend fun cachedBookings(): List<Booking> {
        val p = UserPrefs.getPhone(context)
        val raw = UserPrefs.getCache(context, "bookings_$p")
        if (raw.isBlank()) return emptyList()
        lastError = "OFFLINE"
        return parseBookings(raw)
    }

    private fun parseBookings(raw: String): List<Booking> {
        return try {
            val arr = JsonParser.parseString(raw).asJsonArray
            arr.map { Gson().fromJson(it, Booking::class.java) }
        } catch (e: Exception) {
            try {
                val obj = JsonParser.parseString(raw).asJsonObject
                val arr = obj.getAsJsonArray("bookings") ?: return emptyList()
                arr.map { Gson().fromJson(it, Booking::class.java) }
            } catch (e2: Exception) { emptyList() }
        }
    }

    suspend fun createBooking(
        name: String, phone: String, email: String, tripType: String,
        pickup: String, drop: String, date: String, time: String,
        cabType: String, fare: Double, notes: String = "",
        baseFare: Double = 0.0, fareOffered: Double = 0.0
    ): JsonObject {
        val fcm = UserPrefs.getFcmToken(context)
        val body = mutableMapOf<String, Any>(
            "customer_name" to name, "customer_phone" to phone,
            "user_email" to email, "trip_type" to tripType,
            "pickup_location" to pickup, "drop_location" to drop,
            "pickup_date" to date, "pickup_time" to time,
            "cab_type" to cabType, "total_fare" to fare,
            "special_notes" to notes, "fcm_token" to fcm
        )
        if (baseFare > 0) body["base_fare"] = baseFare
        if (fareOffered > 0) body["fare_offered"] = fareOffered
        return ApiClient.rawPost("api/passenger.php?action=create-booking", body)
    }

    private suspend fun ownerBody(): Map<String, Any> {
        val phone = UserPrefs.getPhone(context)
        val email = UserPrefs.getEmail(context)
        val map = mutableMapOf<String, Any>()
        if (phone.isNotBlank()) map["customer_phone"] = phone
        if (email.isNotBlank()) map["email"] = email
        return map
    }

    suspend fun cancelBooking(bookingId: Int, reason: String = ""): JsonObject {
        val body = ownerBody().toMutableMap()
        body["booking_id"] = bookingId
        if (reason.isNotBlank()) body["reason"] = reason
        return ApiClient.rawPost("api/passenger.php?action=cancel-booking", body)
    }

    suspend fun completeRide(bookingId: Int): JsonObject {
        val body = ownerBody().toMutableMap()
        body["booking_id"] = bookingId
        return ApiClient.rawPost("api/passenger.php?action=complete-ride", body)
    }

    suspend fun boostFare(bookingId: Int, amount: Double): JsonObject {
        val body = ownerBody().toMutableMap()
        body["booking_id"] = bookingId
        body["boost_amount"] = amount
        return ApiClient.rawPost("api/passenger.php?action=boost-fare", body)
    }

    suspend fun rateRide(bookingId: Int, rating: Int, review: String = ""): JsonObject {
        val body = ownerBody().toMutableMap()
        body["booking_id"] = bookingId
        body["rating"] = rating
        body["review_text"] = review
        return ApiClient.rawPost("api/passenger.php?action=rate-ride", body)
    }

    suspend fun updateProfile(name: String? = null, email: String? = null): JsonObject {
        val body = mutableMapOf<String, Any>()
        if (name != null) body["name"] = name
        if (email != null) body["email"] = email
        return ApiClient.rawPost("api/passenger.php?action=update_profile", body)
    }

    suspend fun saveFcmTokenToServer(token: String) {
        try {
            val phone = UserPrefs.getPhone(context)
            val email = UserPrefs.getEmail(context)
            ApiClient.rawPost("api/passenger.php?action=save_fcm_token", mapOf(
                "fcm_token" to token, "user_mobile" to phone, "user_email" to email
            ))
        } catch (_: Exception) {}
    }

    suspend fun respondFareProposal(bookingId: Int, response: String): JsonObject {
        val body = ownerBody().toMutableMap()
        body["booking_id"] = bookingId
        body["response"] = response
        return ApiClient.rawPost("api/passenger.php?action=respond-fare", body)
    }

    suspend fun getRideOffers(): List<DriverOffer> {
        return try {
            val obj = ApiClient.rawGet("api/passenger.php?action=ride-offers")
            val arr = obj.getAsJsonArray("offers") ?: return emptyList()
            arr.map { Gson().fromJson(it, DriverOffer::class.java) }
        } catch (e: Exception) { setErr(e); emptyList() }
    }

    suspend fun acceptRideOffer(offerId: Int): JsonObject {
        return ApiClient.rawPost("api/passenger.php?action=accept-offer", mapOf("offer_id" to offerId))
    }

    suspend fun getNotificationHistory(): List<JsonObject> {
        return try {
            val obj = ApiClient.rawGet("api/passenger.php?action=notification-history")
            val arr = obj.getAsJsonArray("notifications") ?: return cachedNotifications()
            val list = (0 until arr.size()).map { arr[it].asJsonObject }
            if (list.isNotEmpty()) {
                UserPrefs.saveCache(context, "notifs", arr.toString())
                list
            } else cachedNotifications()
        } catch (e: Exception) { setErr(e); cachedNotifications() }
    }

    private suspend fun cachedNotifications(): List<JsonObject> {
        val raw = UserPrefs.getCache(context, "notifs")
        if (raw.isBlank()) return emptyList()
        lastError = "OFFLINE"
        return try {
            val arr = JsonParser.parseString(raw).asJsonArray
            (0 until arr.size()).map { arr[it].asJsonObject }
        } catch (_: Exception) { emptyList() }
    }
}
