package com.pavancab.niranjan.model

import com.google.gson.annotations.SerializedName

data class PickupPlace(
    val id: Int = 0,
    val name: String = "",
    val type: String = ""
)

data class DropFare(
    val id: Int = 0,
    val destination: String = "",
    val distance: String = "",
    @SerializedName("sedan_fare") val sedanFare: Double = 0.0,
    @SerializedName("suv_fare") val suvFare: Double = 0.0
)

data class HourlyFare(
    val id: Int = 0,
    @SerializedName("cab_type") val cabType: String = "",
    val hours: Int = 0,
    val price: Double = 0.0,
    @SerializedName("place_id") val placeId: Int = 0,
    @SerializedName("place_name") val placeName: String = ""
)

data class HourlyExtra(
    @SerializedName("km_rate") val kmRate: Double = 0.0,
    @SerializedName("hr_rate") val hrRate: Double = 0.0,
    @SerializedName("night_rate") val nightRate: Double = 0.0
)

data class Tour(
    val id: Int = 0,
    @SerializedName("tour_name") val tourName: String = "",
    val title: String = "",
    val desc: String = "",
    val duration: String = "",
    val inclusions: List<String> = emptyList(),
    val Sedan: Double = 0.0,
    val Ertiga: Double = 0.0,
    val SUV: Double = 0.0,
    val Crysta: Double = 0.0,
    @SerializedName("place_id") val placeId: Int = 0,
    @SerializedName("place_name") val placeName: String = ""
)

data class Booking(
    val id: Int = 0,
    @SerializedName("booking_ref") val bookingRef: String = "",
    @SerializedName("user_email") val userEmail: String = "",
    @SerializedName("customer_name") val customerName: String = "",
    @SerializedName("customer_phone") val customerPhone: String = "",
    @SerializedName("trip_type") val tripType: String = "",
    @SerializedName("pickup_location") val pickupLocation: String = "",
    @SerializedName("drop_location") val dropLocation: String = "",
    @SerializedName("pickup_date") val pickupDate: String = "",
    @SerializedName("pickup_time") val pickupTime: String = "",
    @SerializedName("cab_type") val cabType: String = "",
    @SerializedName("total_fare") val totalFare: Double = 0.0,
    @SerializedName("base_fare") val baseFare: Double = 0.0,
    @SerializedName("user_offered_fare") val userOfferedFare: Double = 0.0,
    val status: String = "",
    @SerializedName("special_notes") val specialNotes: String = "",
    @SerializedName("driver_name") val driverName: String? = null,
    @SerializedName("driver_phone") val driverPhone: String? = null,
    @SerializedName("driver_rating") val driverRating: Double? = null,
    @SerializedName("vehicle_number") val vehicleNumber: String? = null,
    @SerializedName("vehicle_model") val vehicleModel: String? = null,
    @SerializedName("user_rating") val userRating: Int? = null,
    @SerializedName("user_review") val userReview: String? = null,
    @SerializedName("rated_at") val ratedAt: String? = null,
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("updated_at") val updatedAt: String = "",
    @SerializedName("proposed_fare") val proposedFare: Double = 0.0,
    @SerializedName("fare_proposal_status") val fareProposalStatus: String = "",
    @SerializedName("fare_proposed_by") val fareProposedBy: String = "",
    @SerializedName("fare_proposal_reason") val fareProposalReason: String? = null
)

data class User(
    val id: Int = 0,
    val name: String = "",
    val mobile: String = "",
    val email: String = "",
    val role: String = "",
    @SerializedName("isAdmin") val isAdmin: Boolean = false,
    @SerializedName("isTeam") val isTeam: Boolean = false,
    @SerializedName("isLoggedIn") val isLoggedIn: Boolean = false
)

data class DriverOffer(
    val id: Int = 0,
    @SerializedName("booking_id") val bookingId: Int = 0,
    @SerializedName("driver_id") val driverId: Int = 0,
    @SerializedName("driver_name") val driverName: String = "Driver",
    @SerializedName("driver_phone") val driverPhone: String = "",
    @SerializedName("vehicle_number") val vehicleNumber: String = "",
    @SerializedName("offer_amount") val offerAmount: Double = 0.0,
    @SerializedName("offer_note") val offerNote: String = "",
    val status: String = "PENDING",
    @SerializedName("created_at") val createdAt: String = ""
)

data class ApiResponse<T>(
    val success: Boolean = false,
    val message: String = "",
    val error: String? = null,
    val user: User? = null,
    val isAdmin: Boolean = false,
    val isTeam: Boolean = false,
    val booking: Booking? = null,
    @SerializedName("dev_otp") val devOtp: String? = null,
    @SerializedName("wa_sent") val waSent: Boolean? = null,
    val phone: String? = null,
    val redirect: String? = null,
    val pickups: List<PickupPlace>? = null,
    val drops: List<DropFare>? = null,
    val tours: List<Tour>? = null,
    val fares: List<HourlyFare>? = null,
    val bookings: List<Booking>? = null
)
