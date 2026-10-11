buildscript {
    repositories {
        maven("https://maven.aliyun.com/repository/google")
        maven("https://maven.aliyun.com/repository/public")
        mavenCentral()
    }
    dependencies {
        // AGP 9 内置 Kotlin 版本较旧，这里升级 KGP（官方迁移说明允许的升级方式）。
        // 版本必须与 gradle/libs.versions.toml 的 kotlin 保持一致。
        classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:2.4.21")
    }
}

plugins {
    alias(libs.plugins.android.application) apply false
    alias(libs.plugins.kotlin.compose) apply false
    alias(libs.plugins.kotlin.serialization) apply false
    alias(libs.plugins.legacy.kapt) apply false
    alias(libs.plugins.hilt) apply false
}
