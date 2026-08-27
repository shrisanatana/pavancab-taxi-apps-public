package com.pavancab.driver.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore("driver_session")

object UserPrefs {
    private val KEY_DRIVER_ID = intPreferencesKey("driver_id")
    private val KEY_NAME = stringPreferencesKey("name")
    private val KEY_PHONE = stringPreferencesKey("phone")
    private val KEY_CAR_MODEL = stringPreferencesKey("car_model")
    private val KEY_PLATE_NUMBER = stringPreferencesKey("plate_number")
    private val KEY_LOGGED_IN = booleanPreferencesKey("logged_in")
    private val KEY_FCM_TOKEN = stringPreferencesKey("fcm_token")
    private val KEY_SESSION_ID = stringPreferencesKey("phpsessid")

    suspend fun saveLogin(ctx: Context, id: Int, name: String, phone: String, carModel: String, plateNumber: String) {
        ctx.dataStore.edit { prefs ->
            prefs[KEY_DRIVER_ID] = id; prefs[KEY_NAME] = name; prefs[KEY_PHONE] = phone
            prefs[KEY_CAR_MODEL] = carModel; prefs[KEY_PLATE_NUMBER] = plateNumber
            prefs[KEY_LOGGED_IN] = true
        }
    }

    suspend fun saveSession(ctx: Context, sessionId: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_SESSION_ID] = sessionId }
    }

    suspend fun getSession(ctx: Context): String {
        return ctx.dataStore.data.map { prefs -> prefs[KEY_SESSION_ID] ?: "" }.first()
    }

    suspend fun isLoggedIn(ctx: Context): Boolean {
        return ctx.dataStore.data.map { prefs -> prefs[KEY_LOGGED_IN] ?: false }.first()
    }

    suspend fun getDriverId(ctx: Context): Int = ctx.dataStore.data.map { it[KEY_DRIVER_ID] ?: 0 }.first()
    suspend fun getName(ctx: Context): String = ctx.dataStore.data.map { it[KEY_NAME] ?: "" }.first()
    suspend fun getPhone(ctx: Context): String = ctx.dataStore.data.map { it[KEY_PHONE] ?: "" }.first()
    suspend fun getCarModel(ctx: Context): String = ctx.dataStore.data.map { it[KEY_CAR_MODEL] ?: "" }.first()
    suspend fun getPlateNumber(ctx: Context): String = ctx.dataStore.data.map { it[KEY_PLATE_NUMBER] ?: "" }.first()
    suspend fun getFcmToken(ctx: Context): String = ctx.dataStore.data.map { it[KEY_FCM_TOKEN] ?: "" }.first()

    suspend fun saveFcmToken(ctx: Context, token: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_FCM_TOKEN] = token }
    }

    suspend fun saveCarAndPlate(ctx: Context, name: String, carModel: String, plateNumber: String) {
        ctx.dataStore.edit { prefs ->
            if (name.isNotBlank()) prefs[KEY_NAME] = name
            if (carModel.isNotBlank()) prefs[KEY_CAR_MODEL] = carModel
            if (plateNumber.isNotBlank()) prefs[KEY_PLATE_NUMBER] = plateNumber
        }
    }

    suspend fun clear(ctx: Context) {
        ctx.dataStore.edit { prefs -> prefs.clear() }
    }
}
