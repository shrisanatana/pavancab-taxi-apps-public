package com.pavancab.driver.network

import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import java.util.concurrent.ConcurrentHashMap

class SessionCookieJar : CookieJar {
    private val cookieStore = ConcurrentHashMap<String, MutableList<Cookie>>()

    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        val key = url.host
        val existing = cookieStore.getOrPut(key) { mutableListOf() }
        for (cookie in cookies) {
            existing.removeAll { it.name == cookie.name }
            existing.add(cookie)
        }
    }

    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        return cookieStore[url.host]?.filter { !it.hasExpired() } ?: emptyList()
    }

    private fun Cookie.hasExpired(): Boolean = expiresAt < System.currentTimeMillis()

    fun clear() { cookieStore.clear() }

    fun restoreSession(sessionId: String) {
        if (sessionId.isBlank()) return
        val cookie = Cookie.Builder()
            .domain("pavancab.com").path("/").name("PHPSESSID").value(sessionId).httpOnly().build()
        val existing = cookieStore.getOrPut("pavancab.com") { mutableListOf() }
        existing.removeAll { it.name == "PHPSESSID" }
        existing.add(cookie)
    }

    fun hasSession(): Boolean = cookieStore.values.any { cookies ->
        cookies.any { it.name == "PHPSESSID" && !it.hasExpired() }
    }

    fun getSessionId(): String = cookieStore.values.flatMap { it }
        .firstOrNull { it.name == "PHPSESSID" }?.value ?: ""
}
