# ProGuard rules for PavanCab
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable

# App classes
-keep class com.pavancab.niranjan.model.** { *; }
-keep class com.pavancab.niranjan.service.** { *; }
-keep class com.pavancab.niranjan.network.** { *; }
-keep class com.pavancab.niranjan.data.** { *; }

# Retrofit
-keepattributes Signature
-keep class retrofit2.** { *; }
-keepclasseswithmembers class * {
    @retrofit2.http.* <methods>;
}

# Gson
-keep class com.google.gson.** { *; }
-keep class com.pavancab.niranjan.model.** { *; }
-keepclassmembers class com.pavancab.niranjan.model.** { *; }

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**

# Firebase
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**
