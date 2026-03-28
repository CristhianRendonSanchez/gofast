package com.example.gofast

import android.annotation.SuppressLint
import android.app.AlertDialog
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.util.Log
import android.webkit.CookieManager
import android.webkit.JsResult
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.WindowCompat

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private val TAG = "GoFastApp"

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Mostrar la barra de estado y navegación normalmente (NO pantalla completa)
        WindowCompat.setDecorFitsSystemWindows(window, true)

        // Crear WebView
        webView = WebView(this)
        setContentView(webView)

        // Manejar botón atrás
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })

        // Configurar WebView
        webView.apply {
            settings.apply {
                javaScriptEnabled = true
                domStorageEnabled = true
                allowFileAccess = true
                allowContentAccess = true
                loadWithOverviewMode = true
                useWideViewPort = true
                javaScriptCanOpenWindowsAutomatically = true
                setSupportMultipleWindows(false)
                mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                cacheMode = WebSettings.LOAD_DEFAULT
                
                // User agent para mejor compatibilidad
                userAgentString = userAgentString + " GoFastApp/1.1"
            }
            
            isFocusable = true
            isFocusableInTouchMode = true

            // Cliente para navegación
            webViewClient = object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                    val url = request?.url?.toString() ?: return false
                    return handleExternalLinks(url)
                }

                @Deprecated("Deprecated in Java")
                override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                    return handleExternalLinks(url ?: "")
                }

                private fun handleExternalLinks(url: String): Boolean {
                    if (url.startsWith("http://") || url.startsWith("https://")) {
                        if (url.contains("wa.me") || url.contains("whatsapp.com")) {
                            return launchExternalIntent(url)
                        }
                        return false 
                    }
                    return launchExternalIntent(url)
                }

                private fun launchExternalIntent(url: String): Boolean {
                    try {
                        val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                        startActivity(intent)
                        return true
                    } catch (e: Exception) {
                        Toast.makeText(this@MainActivity, "No se pudo abrir la aplicación", Toast.LENGTH_SHORT).show()
                        return true
                    }
                }
            }
            
            // Cliente para JavaScript (diálogos confirm/alert)
            webChromeClient = object : WebChromeClient() {
                
                override fun onJsConfirm(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                    Log.d(TAG, "onJsConfirm llamado: $message")
                    
                    try {
                        // Usar android.app.AlertDialog (NO androidx) para mejor compatibilidad
                        val builder = AlertDialog.Builder(this@MainActivity)
                        builder.setTitle("Go Fast")
                        builder.setMessage(message ?: "¿Desea continuar?")
                        builder.setPositiveButton("Aceptar") { dialog, _ ->
                            Log.d(TAG, "Usuario aceptó")
                            result?.confirm()
                            dialog.dismiss()
                        }
                        builder.setNegativeButton("Cancelar") { dialog, _ ->
                            Log.d(TAG, "Usuario canceló")
                            result?.cancel()
                            dialog.dismiss()
                        }
                        builder.setOnCancelListener {
                            result?.cancel()
                        }
                        builder.setCancelable(false)
                        
                        val dialog = builder.create()
                        dialog.show()
                        
                        Log.d(TAG, "Diálogo mostrado correctamente")
                    } catch (e: Exception) {
                        Log.e(TAG, "Error mostrando diálogo: ${e.message}")
                        result?.cancel()
                    }
                    
                    return true
                }

                override fun onJsAlert(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                    Log.d(TAG, "onJsAlert llamado: $message")
                    
                    try {
                        val builder = AlertDialog.Builder(this@MainActivity)
                        builder.setTitle("Go Fast")
                        builder.setMessage(message ?: "")
                        builder.setPositiveButton("OK") { dialog, _ ->
                            result?.confirm()
                            dialog.dismiss()
                        }
                        builder.setCancelable(false)
                        
                        val dialog = builder.create()
                        dialog.show()
                    } catch (e: Exception) {
                        Log.e(TAG, "Error mostrando alerta: ${e.message}")
                        result?.confirm()
                    }
                    
                    return true
                }
            }
        }

        // Configurar cookies
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true)

        // Cargar la web
        webView.loadUrl("https://gofastdomicilios.com/")
        
        Log.d(TAG, "WebView iniciado correctamente")
    }
    
    override fun onDestroy() {
        webView.destroy()
        super.onDestroy()
    }
}
