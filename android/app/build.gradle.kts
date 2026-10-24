plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
    alias(libs.plugins.legacy.kapt)
    alias(libs.plugins.hilt)
}

android {
    namespace = "com.qichuang.changliao"
    compileSdk = 37

    defaultConfig {
        applicationId = "com.qichuang.changliao"
        minSdk = 26
        targetSdk = 37
        versionCode = 2
        versionName = "3.2"
    }

    buildTypes {
        debug {
            // 后端跑在 WSL2 里时，模拟器的 10.0.2.2（宿主机）走不通 —— 实测
            //
            // SocketTimeoutException: failed to connect to /10.0.2.2 (port 9501)，而同一时刻
            // Windows 侧 curl 127.0.0.1:9501 是 200。跟 WSL 的 mirrored 网络有关，没深究。
            // 所以默认走 **adb reverse**，应用访问 127.0.0.1：
            //   adb reverse tcp:9501 tcp:9501
            // 真机调试同样走这条路（真机也连不到 10.0.2.2）。
            val apiBaseUrl = providers.gradleProperty("apiBaseUrl")
                .getOrElse("http://127.0.0.1:9501")
            // WebSocket 同理（后端 ws 服务在 9502）：
            //   ./gradlew assembleDebug -PwsBaseUrl=ws://127.0.0.1:9502/ws
            val wsUrl = providers.gradleProperty("wsBaseUrl")
                .getOrElse("ws://127.0.0.1:9502/ws")
            buildConfigField("String", "API_BASE_URL", "\"$apiBaseUrl\"")
            buildConfigField("String", "WS_URL", "\"$wsUrl\"")
        }
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
            buildConfigField("String", "API_BASE_URL", "\"https://api.changliao.example.com\"")
            buildConfigField("String", "WS_URL", "\"wss://api.changliao.example.com/ws\"")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }

    testOptions {
        unitTests {
            // Robolectric 要读 App 的资源（Q0-21 的 Room DAO/迁移测试）
            isIncludeAndroidResources = true
            all { test ->
                // Robolectric 会在跑测试时自己去下 android-all jar，本机 dl.google/maven central 不通，
                // 和 Gradle 一样走阿里云镜像
                test.systemProperty(
                    "robolectric.dependency.repo.url",
                    "https://maven.aliyun.com/repository/public",
                )
            }
        }
    }
}

kapt {
    arguments {
        // Room 把权威表结构导出到 app/schemas/：写 Migration 时照抄它，
        // 不要凭记忆手写建表语句（docs/10 第 8 节第 3 条）
        arg("room.schemaLocation", "$projectDir/schemas")
    }
}

dependencies {
    implementation(platform(libs.androidx.compose.bom))

    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.hilt.navigation.compose)

    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    // ClIcons 用的 Material Icons Rounded 全套（docs/03 第 6 节）
    implementation(libs.androidx.material.icons.extended)
    debugImplementation(libs.androidx.compose.ui.tooling)

    implementation(libs.hilt.android)
    kapt(libs.hilt.compiler)

    implementation(libs.androidx.room.runtime)
    implementation(libs.androidx.room.ktx)
    kapt(libs.androidx.room.compiler)

    implementation(libs.androidx.datastore.preferences)

    implementation(libs.retrofit)
    implementation(libs.retrofit.serialization)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.kotlinx.coroutines.android)

    implementation(libs.coil.compose)
    implementation(libs.coil.network.okhttp)

    testImplementation(libs.junit)
    // Room 的 DAO / 迁移在 JVM 上跑（Robolectric 提供 Android 环境）
    testImplementation(libs.robolectric)
    testImplementation(libs.androidx.test.core)
    testImplementation(libs.kotlinx.coroutines.test)
    testImplementation(libs.mockk)
    testImplementation(libs.turbine)
    testImplementation(libs.okhttp.mockwebserver)
}
