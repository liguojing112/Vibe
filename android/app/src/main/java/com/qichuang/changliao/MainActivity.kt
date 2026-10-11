package com.qichuang.changliao

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.gallery.DesignSystemGalleryScreen
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import dagger.hilt.android.AndroidEntryPoint

/**
 * 唯一的 Activity（见 docs/02 第 4.1 节）。
 * T2.7 起接入真正的导航；现在 debug 包直接进组件预览页（docs/03 第 7 节），release 还是占位页。
 */
@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            ClTheme {
                if (BuildConfig.DEBUG) {
                    DesignSystemGalleryScreen()
                } else {
                    ClPlaceholderScreen()
                }
            }
        }
    }
}

@Composable
private fun ClPlaceholderScreen() {
    Surface(modifier = Modifier.fillMaxSize(), color = ClTheme.colors.surface) {
        Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
            Text(
                text = "畅撩交友 · 工程骨架（T0.4）",
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
            )
        }
    }
}
