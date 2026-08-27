package com.pavancab.niranjan.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "pavancab_session")

object UserPrefs {
    private val KEY_USER_ID = intPreferencesKey("user_id")
    private val KEY_NAME = stringPreferencesKey("user_name")
    private val KEY_PHONE = stringPreferencesKey("user_phone")
    private val KEY_EMAIL = stringPreferencesKey("user_email")
    private val KEY_ROLE = stringPreferencesKey("user_role")
    private val KEY_IS_ADMIN = booleanPreferencesKey("is_admin")
    private val KEY_IS_TEAM = booleanPreferencesKey("is_team")
    private val KEY_LOGGED_IN = booleanPreferencesKey("logged_in")
    private val KEY_FCM_TOKEN = stringPreferencesKey("fcm_token")
    private val KEY_SESSION_ID = stringPreferencesKey("phpsessid")

    suspend fun saveUser(context: Context, userId: Int, name: String, phone: String,
                         email: String, role: String, isAdmin: Boolean, isTeam: Boolean) {
        context.dataStore.edit { prefs ->
            prefs[KEY_USER_ID] = userId
            prefs[KEY_NAME] = name
            prefs[KEY_PHONE] = phone
            prefs[KEY_EMAIL] = email
            prefs[KEY_ROLE] = role
            prefs[KEY_IS_ADMIN] = isAdmin
            prefs[KEY_IS_TEAM] = isTeam
            prefs[KEY_LOGGED_IN] = true
        }
    }

    suspend fun clearUser(context: Context) {
        context.dataStore.edit { prefs ->
            prefs[KEY_LOGGED_IN] = false
            prefs.remove(KEY_USER_ID)
            prefs.remove(KEY_NAME)
            prefs.remove(KEY_PHONE)
            prefs.remove(KEY_EMAIL)
        }
    }

    suspend fun isLoggedIn(context: Context): Boolean {
        return context.dataStore.data.map { prefs -> prefs[KEY_LOGGED_IN] ?: false }.first()
    }

    suspend fun getPhone(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_PHONE] ?: "" }.first()
    }

    suspend fun getEmail(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_EMAIL] ?: "" }.first()
    }

    suspend fun getName(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_NAME] ?: "" }.first()
    }

    suspend fun getRole(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_ROLE] ?: "user" }.first()
    }

    suspend fun isAdmin(context: Context): Boolean {
        return context.dataStore.data.map { prefs -> prefs[KEY_IS_ADMIN] ?: false }.first()
    }

    suspend fun getUserId(context: Context): Int {
        return context.dataStore.data.map { prefs -> prefs[KEY_USER_ID] ?: 0 }.first()
    }

    suspend fun getIsAdmin(context: Context): Boolean {
        return context.dataStore.data.map { prefs -> prefs[KEY_IS_ADMIN] ?: false }.first()
    }

    suspend fun getIsTeam(context: Context): Boolean {
        return context.dataStore.data.map { prefs -> prefs[KEY_IS_TEAM] ?: false }.first()
    }

    suspend fun saveFcmToken(context: Context, token: String) {
        context.dataStore.edit { prefs -> prefs[KEY_FCM_TOKEN] = token }
    }

    suspend fun getFcmToken(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_FCM_TOKEN] ?: "" }.first()
    }

    suspend fun saveSessionId(context: Context, sessionId: String) {
        context.dataStore.edit { prefs -> prefs[KEY_SESSION_ID] = sessionId }
    }

    suspend fun getSessionId(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_SESSION_ID] ?: "" }.first()
    }

    // Persistent remember-token — survives server session death; enables silent auto-login
    private val KEY_AUTO_TOKEN = stringPreferencesKey("remember_token")

    suspend fun saveAutoToken(context: Context, token: String) {
        context.dataStore.edit { prefs -> prefs[KEY_AUTO_TOKEN] = token }
    }

    suspend fun getAutoToken(context: Context): String {
        return context.dataStore.data.map { prefs -> prefs[KEY_AUTO_TOKEN] ?: "" }.first()
    }

    suspend fun clearAutoToken(context: Context) {
        context.dataStore.edit { prefs -> prefs.remove(KEY_AUTO_TOKEN) }
    }

    // ---- Offline cache: last-known-good data so app NEVER shows empty/wrong info offline ----
    suspend fun saveCache(context: Context, key: String, value: String) {
        context.dataStore.edit { prefs -> prefs[stringPreferencesKey("cache_$key")] = value }
    }

    suspend fun getCache(context: Context, key: String): String {
        return context.dataStore.data.map { prefs -> prefs[stringPreferencesKey("cache_$key")] ?: "" }.first()
    }
}
