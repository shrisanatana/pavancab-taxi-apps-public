package com.pavancab.dispatch.model

import com.google.gson.annotations.SerializedName

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
    val status: String = "PENDING",
    @SerializedName("special_notes") val specialNotes: String = "",
    @SerializedName("driver_name") val driverName: String = "",
    @SerializedName("driver_phone") val driverPhone: String = "",
    @SerializedName("driver_rating") val driverRating: Double = 0.0,
    @SerializedName("vehicle_number") val vehicleNumber: String = "",
    @SerializedName("vehicle_model") val vehicleModel: String = "",
    @SerializedName("driver_id") val driverId: Int = 0,
    @SerializedName("driver_decision") val driverDecision: String = "NONE",
    @SerializedName("user_rating") val userRating: Int = 0,
    @SerializedName("user_review") val userReview: String = "",
    @SerializedName("rated_at") val ratedAt: String = "",
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("updated_at") val updatedAt: String = "",
    @SerializedName("proposed_fare") val proposedFare: Double = 0.0,
    @SerializedName("fare_proposal_status") val fareProposalStatus: String = "",
    @SerializedName("fare_proposed_by") val fareProposedBy: String = "",
    @SerializedName("fare_proposal_reason") val fareProposalReason: String = "",
    @SerializedName("booking_source") val bookingSource: String = "app",
    @SerializedName("reminder_sent") val reminderSent: Int = 0,
    @SerializedName("reminder_type") val reminderType: String = "",
    @SerializedName("commission_status") val commissionStatus: String = "",
    @SerializedName("commission_badge") val commissionBadge: String = "NA",
    @SerializedName("passenger_rating") val passengerRating: Int = 0,
    @SerializedName("passenger_review") val passengerReview: String = "",
    @SerializedName("is_frozen") val isFrozen: Int = 0
)

data class Driver(
    val id: Int = 0,
    val name: String = "",
    val phone: String = "",
    @SerializedName("car_model") val carModel: String = "",
    @SerializedName("plate_number") val plateNumber: String = "",
    val status: String = "available",
    val rating: Double = 5.0,
    @SerializedName("total_ratings") val totalRatings: Int = 0,
    @SerializedName("fcm_token") val fcmToken: String = "",
    @SerializedName("is_online") val isOnline: Int = 0,
    @SerializedName("is_approved") val isApproved: Int = 1
)

data class TeamMember(
    val id: Int = 0,
    @SerializedName("member_name") val memberName: String = "",
    @SerializedName("member_phone") val memberPhone: String = "",
    @SerializedName("member_email") val memberEmail: String = "",
    val role: String = "team",
    @SerializedName("is_active") val isActive: Int = 1,
    @SerializedName("added_by_email") val addedByEmail: String = "",
    @SerializedName("invited_at") val invitedAt: String = ""
)

data class User(
    val id: Int = 0,
    val name: String = "",
    val mobile: String = "",
    val email: String = "",
    val role: String = "user",
    @SerializedName("isAdmin") val isAdmin: Boolean = false,
    @SerializedName("isTeam") val isTeam: Boolean = false,
    @SerializedName("isLoggedIn") val isLoggedIn: Boolean = false
)

data class DashboardStats(
    val totalBookings: Int = 0,
    val todayBookings: Int = 0,
    val activeRides: Int = 0,
    val completedToday: Int = 0,
    val cancelledToday: Int = 0,
    val totalDrivers: Int = 0,
    val availableDrivers: Int = 0,
    val totalRevenue: Double = 0.0
)

data class ActiveUser(
    @SerializedName("user_id") val userId: Int = 0,
    val name: String = "",
    val mobile: String = "",
    val email: String = "",
    @SerializedName("is_online") val isOnline: Int = 0,
    @SerializedName("is_banned") val isBanned: Int = 0,
    @SerializedName("live_app_status") val liveAppStatus: String = "OFFLINE_CLOSED",
    @SerializedName("last_active_at") val lastActiveAt: String = "",
    @SerializedName("total_bookings") val totalBookings: Int = 0,
    @SerializedName("completed_bookings") val completedBookings: Int = 0,
    @SerializedName("cancelled_bookings") val cancelledBookings: Int = 0,
    @SerializedName("total_spent") val totalSpent: Double = 0.0
)

data class BookingsPage(
    val bookings: List<Booking> = emptyList(),
    val total: Int = 0,
    val pages: Int = 0
)

data class CommissionDay(
    @SerializedName("ride_date") val rideDate: String = "",
    @SerializedName("ride_count") val rideCount: Int = 0,
    @SerializedName("total_fare") val totalFare: Double = 0.0,
    val commission: Int = 0
)

data class CommissionReport(
    val daily: List<CommissionDay> = emptyList(),
    val totalCommission: Double = 0.0,
    val totalRides: Int = 0,
    val commissionPerRide: Int = 300
)

data class RideReport(
    val id: Int = 0,
    @SerializedName("booking_id") val bookingId: Int = 0,
    @SerializedName("reporter_phone") val reporterPhone: String = "",
    @SerializedName("reporter_name") val reporterName: String = "",
    @SerializedName("issue_category") val issueCategory: String = "SAFETY",
    val severity: String = "MEDIUM",
    val description: String = "",
    @SerializedName("ride_status_at_report") val rideStatusAtReport: String = "ONGOING",
    val status: String = "PENDING",
    @SerializedName("admin_response") val adminResponse: String = "",
    @SerializedName("booking_ref") val bookingRef: String = "",
    @SerializedName("pickup_location") val pickupLocation: String = "",
    @SerializedName("drop_location") val dropLocation: String = "",
    @SerializedName("driver_name") val driverName: String = "",
    @SerializedName("driver_phone") val driverPhone: String = "",
    @SerializedName("vehicle_number") val vehicleNumber: String = "",
    @SerializedName("created_at") val createdAt: String = ""
)

data class DriverDetail(
    val driver: Driver = Driver(),
    val bookings: List<Booking> = emptyList(),
    val stats: DriverStats = DriverStats()
)

data class DriverStats(
    @SerializedName("total_rides") val totalRides: Int = 0,
    val completed: Int = 0,
    val cancelled: Int = 0,
    @SerializedName("total_earnings") val totalEarnings: Double = 0.0,
    @SerializedName("commission_due_count") val commissionDueCount: Int = 0,
    @SerializedName("commission_paid_count") val commissionPaidCount: Int = 0
)

data class UserDetail(
    @SerializedName("user_id") val userId: Int = 0,
    val name: String = "",
    val mobile: String = "",
    val email: String = "",
    @SerializedName("is_online") val isOnline: Int = 0,
    @SerializedName("is_banned") val isBanned: Int = 0,
    @SerializedName("last_active_at") val lastActiveAt: String = "",
    @SerializedName("created_at") val createdAt: String = "",
    @SerializedName("total_bookings") val totalBookings: Int = 0,
    @SerializedName("completed_bookings") val completedBookings: Int = 0,
    @SerializedName("cancelled_bookings") val cancelledBookings: Int = 0,
    @SerializedName("total_spent") val totalSpent: Double = 0.0,
    @SerializedName("fcm_tokens") val fcmTokens: List<FcmToken> = emptyList(),
    val bookings: List<Booking> = emptyList()
)

data class FcmToken(
    @SerializedName("fcm_token") val fcmToken: String = "",
    @SerializedName("is_online") val isOnline: Int = 0,
    @SerializedName("device_info") val deviceInfo: String = "",
    @SerializedName("last_active_at") val lastActiveAt: String = "",
    @SerializedName("updated_at") val updatedAt: String = ""
)
