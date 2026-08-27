package com.pavancab.dispatch.data

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.*
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore("dispatch_session")

object UserPrefs {
    private val KEY_USER_ID = intPreferencesKey("user_id")
    private val KEY_NAME = stringPreferencesKey("name")
    private val KEY_PHONE = stringPreferencesKey("phone")
    private val KEY_EMAIL = stringPreferencesKey("email")
    private val KEY_ROLE = stringPreferencesKey("role")
    private val KEY_IS_ADMIN = booleanPreferencesKey("is_admin")
    private val KEY_IS_TEAM = booleanPreferencesKey("is_team")
    private val KEY_LOGGED_IN = booleanPreferencesKey("logged_in")
    private val KEY_FCM_TOKEN = stringPreferencesKey("fcm_token")
    private val KEY_SESSION_ID = stringPreferencesKey("phpsessid")
    private val KEY_RINGTONE = stringPreferencesKey("ringtone_uri")
    private val KEY_RINGTONE_REPEAT = intPreferencesKey("ringtone_repeat")
    private val KEY_RINGTONE_PHONE = stringPreferencesKey("ringtone_uri_phone")
    private val KEY_RINGTONE_CANCELLED = stringPreferencesKey("ringtone_uri_cancelled")

    suspend fun saveLogin(ctx: Context, id: Int, name: String, phone: String, email: String, role: String, isAdmin: Boolean, isTeam: Boolean) {
        ctx.dataStore.edit { prefs ->
            prefs[KEY_USER_ID] = id; prefs[KEY_NAME] = name; prefs[KEY_PHONE] = phone
            prefs[KEY_EMAIL] = email; prefs[KEY_ROLE] = role; prefs[KEY_IS_ADMIN] = isAdmin
            prefs[KEY_IS_TEAM] = isTeam; prefs[KEY_LOGGED_IN] = true
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

    suspend fun getName(ctx: Context): String = ctx.dataStore.data.map { it[KEY_NAME] ?: "" }.first()
    suspend fun saveName(ctx: Context, name: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_NAME] = name }
    }
    suspend fun getEmail(ctx: Context): String = ctx.dataStore.data.map { it[KEY_EMAIL] ?: "" }.first()
    suspend fun saveEmail(ctx: Context, email: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_EMAIL] = email }
    }
    suspend fun getPhone(ctx: Context): String = ctx.dataStore.data.map { it[KEY_PHONE] ?: "" }.first()
    suspend fun getRole(ctx: Context): String = ctx.dataStore.data.map { it[KEY_ROLE] ?: "" }.first()
    suspend fun isAdmin(ctx: Context): Boolean = ctx.dataStore.data.map { it[KEY_IS_ADMIN] ?: false }.first()
    suspend fun isTeam(ctx: Context): Boolean = ctx.dataStore.data.map { it[KEY_IS_TEAM] ?: false }.first()
    suspend fun getFcmToken(ctx: Context): String = ctx.dataStore.data.map { it[KEY_FCM_TOKEN] ?: "" }.first()

    suspend fun saveFcmToken(ctx: Context, token: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_FCM_TOKEN] = token }
    }

    suspend fun getRingtoneUri(ctx: Context): String = ctx.dataStore.data.map { it[KEY_RINGTONE] ?: "" }.first()

    suspend fun saveRingtoneUri(ctx: Context, uri: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_RINGTONE] = uri }
        ctx.getSharedPreferences("dispatch_session", Context.MODE_PRIVATE).edit().putString("ringtone_uri", uri).apply()
    }

    suspend fun getRingtoneRepeat(ctx: Context): Int = ctx.dataStore.data.map { it[KEY_RINGTONE_REPEAT] ?: 3 }.first()

    suspend fun saveRingtoneRepeat(ctx: Context, count: Int) {
        ctx.dataStore.edit { prefs -> prefs[KEY_RINGTONE_REPEAT] = count }
        ctx.getSharedPreferences("dispatch_session", Context.MODE_PRIVATE).edit().putInt("ringtone_repeat", count).apply()
    }

    suspend fun getRingtoneUriPhone(ctx: Context): String = ctx.dataStore.data.map { it[KEY_RINGTONE_PHONE] ?: "" }.first()
    suspend fun saveRingtoneUriPhone(ctx: Context, uri: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_RINGTONE_PHONE] = uri }
        ctx.getSharedPreferences("dispatch_session", Context.MODE_PRIVATE).edit().putString("ringtone_uri_phone", uri).apply()
    }

    suspend fun getRingtoneUriCancelled(ctx: Context): String = ctx.dataStore.data.map { it[KEY_RINGTONE_CANCELLED] ?: "" }.first()
    suspend fun saveRingtoneUriCancelled(ctx: Context, uri: String) {
        ctx.dataStore.edit { prefs -> prefs[KEY_RINGTONE_CANCELLED] = uri }
        ctx.getSharedPreferences("dispatch_session", Context.MODE_PRIVATE).edit().putString("ringtone_uri_cancelled", uri).apply()
    }

    suspend fun clear(ctx: Context) {
        ctx.dataStore.edit { prefs -> prefs.clear() }
    }
}
