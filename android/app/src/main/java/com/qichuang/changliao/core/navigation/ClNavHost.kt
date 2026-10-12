package com.qichuang.changliao.core.navigation

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.navigation.NavGraphBuilder
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.qichuang.changliao.core.designsystem.gallery.DesignSystemGalleryScreen
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.auth.AgreementRoute
import com.qichuang.changliao.feature.auth.LoginRoute
import com.qichuang.changliao.feature.auth.RegisterRoute
import com.qichuang.changliao.feature.common.PlaceholderPage
import com.qichuang.changliao.feature.discover.FilterRoute
import com.qichuang.changliao.feature.main.MainScreen
import com.qichuang.changliao.feature.profile.EditProfileRoute
import com.qichuang.changliao.feature.profile.UserDetailRoute
import com.qichuang.changliao.feature.relation.FansRoute
import com.qichuang.changliao.feature.relation.VisitorsRoute

/**
 * 全 App 唯一导航图（docs/02 第 4.3 节）：所有页面的路由都在这里注册。
 *
 * 除了已经实现的页面，其余统一挂 [PlaceholderPage]（「开发中」）；
 * 每个页面任务完成后，把对应的 `placeholder(...)` 换成真实页面即可。
 */
@Composable
fun ClNavHost(
    startDestination: String,
    modifier: Modifier = Modifier,
) {
    val navController = rememberNavController()

    NavHost(
        navController = navController,
        startDestination = startDestination,
        // 底部安全区在整棵导航树上处理一次：页面不用各自操心系统导航栏
        modifier = modifier
            .fillMaxSize()
            .background(ClTheme.colors.bgCream)
            .windowInsetsPadding(WindowInsets.navigationBars),
    ) {
        composable(Routes.LOGIN) {
            LoginRoute(
                onGoMain = { navController.navigate(Routes.MAIN) { popUpTo(Routes.LOGIN) { inclusive = true } } },
                onRegister = { navController.navigate(Routes.REGISTER) },
                onOpenAgreement = { type -> navController.navigate(Routes.agreement(type)) },
            )
        }

        composable(Routes.REGISTER) {
            RegisterRoute(
                onGoMain = { navController.navigate(Routes.MAIN) { popUpTo(Routes.LOGIN) { inclusive = true } } },
                onBack = { navController.popBackStack() },
            )
        }

        composable(Routes.MAIN) {
            MainScreen(onOpen = { route -> navController.navigate(route) })
        }

        composable(Routes.GALLERY) { DesignSystemGalleryScreen() }

        composable(Routes.AGREEMENT) { entry ->
            AgreementRoute(
                type = entry.arguments?.getString("type").orEmpty(),
                onBack = { navController.popBackStack() },
            )
        }

        composable(Routes.USER_DETAIL) { entry ->
            UserDetailRoute(
                userId = entry.arguments?.getString("user_id")?.toLongOrNull() ?: 0L,
                onBack = { navController.popBackStack() },
                onChat = { navController.navigate(Routes.chat(it)) },
            )
        }

        placeholder(navController, Routes.CHAT, "私信聊天（P06）")
        composable(Routes.FILTER) {
            FilterRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.VISITORS) {
            VisitorsRoute(
                onBack = { navController.popBackStack() },
                onOpenUser = { navController.navigate(Routes.userDetail(it)) },
                onOpenVip = { navController.navigate(Routes.VIP) },
            )
        }
        composable(Routes.LIKED_ME) {
            FansRoute(
                onBack = { navController.popBackStack() },
                onOpenUser = { navController.navigate(Routes.userDetail(it)) },
                onOpenVip = { navController.navigate(Routes.VIP) },
            )
        }
        placeholder(navController, Routes.NOTICES, "系统消息（P10）")
        placeholder(navController, Routes.DAILY_TASK, "每日任务（P11）")
        placeholder(navController, Routes.VIP, "会员中心（P12）")
        placeholder(navController, Routes.COIN_WALLET, "金币钱包（P13）")
        composable(Routes.EDIT_PROFILE) {
            EditProfileRoute(onBack = { navController.popBackStack() })
        }
        placeholder(navController, Routes.SETTINGS, "设置（P15）")
        placeholder(navController, Routes.VOICE_HALL, "语音大厅（P16）")
        placeholder(navController, Routes.CALL_CONFIRM, "拨打前价格确认（P17）")
        placeholder(navController, Routes.CALL_WAITING, "等待接通（P18）")
        placeholder(navController, Routes.VOICE_CALL, "一对一语音通话（P19）")
        placeholder(navController, Routes.VIDEO_CALL, "一对一视频通话（P20）")
        placeholder(navController, Routes.CALL_BILL, "结束通话账单（P21）")
        placeholder(navController, Routes.RECHARGE, "金币充值（P22）")
        placeholder(navController, Routes.GIFT_PANEL, "礼物面板（P23）")
        placeholder(navController, Routes.COIN_LOGS, "金币收支明细（P24）")
        placeholder(navController, Routes.CALL_RECORDS, "通话记录（P25）")
        placeholder(navController, Routes.INCOME, "我的收益（P26）")
        placeholder(navController, Routes.LEVEL_SETTING, "我的等级与收费（P27）")
        placeholder(navController, Routes.WITHDRAW, "收益提现（P28）")
        placeholder(navController, Routes.REAL_NAME, "实名认证（P29）")
    }
}

private fun NavGraphBuilder.placeholder(
    navController: NavHostController,
    route: String,
    title: String,
) {
    composable(route) {
        PlaceholderPage(title = title, onBack = { navController.popBackStack() })
    }
}
