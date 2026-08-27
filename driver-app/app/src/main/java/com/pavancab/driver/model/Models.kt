package com.pavancab.driver.model

import com.google.gson.annotations.SerializedName

data class DriverProfile(
    val id: Int = 0,
    val name: String = "",
    val phone: String = "",
    @SerializedName("car_model") val carModel: String = "",
    @SerializedName("plate_number") val plateNumber: String = "",
    val rating: Double = 5.0,
    @SerializedName("total_ratings") val totalRatings: Int = 0,
    @SerializedName("is_online") val isOnline: Int = 0
)

data class Booking(
    val id: Int = 0,
    @SerializedName("booking_ref") val bookingRef: String = "",
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
    val status: String = "PENDING",
    @SerializedName("special_notes") val specialNotes: String = "",
    @SerializedName("driver_name") val driverName: String = "",
    @SerializedName("driver_phone") val driverPhone: String = "",
    @SerializedName("driver_id") val driverId: Int = 0,
    @SerializedName("driver_decision") val driverDecision: String = "NONE",
    @SerializedName("user_rating") val userRating: Int = 0,
    @SerializedName("user_review") val userReview: String = "",
    @SerializedName("rated_at") val ratedAt: String = "",
    @SerializedName("passenger_rating") val passengerRating: Int = 0,
    @SerializedName("passenger_review") val passengerReview: String = "",
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("updated_at") val updatedAt: String = ""
)

data class EarningsSummary(
    val todayRides: Int = 0,
    val todayEarnings: Double = 0.0,
    val weekRides: Int = 0,
    val weekEarnings: Double = 0.0,
    val monthRides: Int = 0,
    val monthEarnings: Double = 0.0,
    val commissionPerRide: Int = 300
)

data class QuickRide(
    val id: Int = 0,
    @SerializedName("booking_ref") val bookingRef: String = "",
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
    val status: String = "PENDING",
    @SerializedName("special_notes") val specialNotes: String = "",
    @SerializedName("can_offer") val canOffer: Int = 0,
    @SerializedName("admin_proposal_pending") val adminProposalPending: Int = 0,
    @SerializedName("my_offer_amount") val myOfferAmount: Double = 0.0,
    @SerializedName("offer_count") val offerCount: Int = 0,
    @SerializedName("max_offers") val maxOffers: Int = 5,
    @SerializedName("offer_closed") val offerClosed: Int = 0,
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("window_active") val windowActive: Int = 0,
    @SerializedName("window_remaining") val windowRemaining: Long = 0,
    @SerializedName("window_seconds") val windowSeconds: Long = 0,
    @SerializedName("created_at_epoch") val createdAtEpoch: Long = 0
)
