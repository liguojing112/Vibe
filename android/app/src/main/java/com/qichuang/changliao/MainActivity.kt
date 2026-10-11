package com.qichuang.changliao

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import dagger.hilt.android.AndroidEntryPoint

/**
 * 唯一的 Activity（见 docs/02 第 4.1 节）。
 * T0.4 只显示占位页；导航与真实页面从 T2.7 起接入。
 */
@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ClPlaceholderScreen()
        }
    }
}

@Composable
private fun ClPlaceholderScreen() {
    Surface(modifier = Modifier.fillMaxSize()) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Text(
                text = "畅撩交友 · 工程骨架（T0.4）",
                style = MaterialTheme.typography.bodyLarge,
            )
        }
    }
}
