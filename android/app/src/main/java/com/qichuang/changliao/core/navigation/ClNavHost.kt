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
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.compose.rememberNavController
import com.qichuang.changliao.core.designsystem.gallery.DesignSystemGalleryScreen
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.auth.AgreementRoute
import com.qichuang.changliao.feature.auth.LoginRoute
import com.qichuang.changliao.feature.auth.RegisterRoute
import com.qichuang.changliao.feature.chat.ChatRoute
import com.qichuang.changliao.feature.gift.GiftPanelRoute
import com.qichuang.changliao.feature.common.PlaceholderPage
import com.qichuang.changliao.feature.discover.FilterRoute
import com.qichuang.changliao.feature.main.MainScreen
import com.qichuang.changliao.feature.profile.EditProfileRoute
import com.qichuang.changliao.feature.profile.UserDetailRoute
import com.qichuang.changliao.feature.relation.FansRoute
import com.qichuang.changliao.feature.wallet.CoinLogsRoute
import com.qichuang.changliao.feature.wallet.RechargeRoute
import com.qichuang.changliao.feature.wallet.WalletRoute
import com.qichuang.changliao.feature.income.IncomeLevelRoute
import com.qichuang.changliao.feature.income.IncomeRoute
import com.qichuang.changliao.feature.realname.RealNameRoute
import com.qichuang.changliao.core.data.model.NoticeCategory
import com.qichuang.changliao.feature.notice.NoticeRoute
import com.qichuang.changliao.feature.relation.VisitorsRoute
import com.qichuang.changliao.feature.settings.BlockListRoute
import com.qichuang.changliao.feature.settings.SettingsRoute
import com.qichuang.changliao.feature.task.DailyTaskRoute
import com.qichuang.changliao.feature.vip.VipCenterRoute
import com.qichuang.changliao.feature.withdraw.WithdrawRoute

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
    // 登录态盯着：token 一没（退出登录 / 注销 / 401 刷新也失败）就清本地状态、回登录页
    val session: SessionViewModel = hiltViewModel()
    val loggedIn by session.loggedIn.collectAsStateWithLifecycle()

    LaunchedEffect(loggedIn) {
        if (!loggedIn && navController.currentDestination?.route != Routes.LOGIN) {
            session.clearSession()
            navController.navigate(Routes.LOGIN) {
                // 把整棵栈清掉：登录页按返回键不该回到已经登出的页面
                popUpTo(navController.graph.id) { inclusive = true }
            }
        }
    }

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
                onOpenGift = { navController.navigate(Routes.giftPanel(it)) },
            )
        }

        composable(Routes.CHAT) { entry ->
            ChatRoute(
                userId = entry.arguments?.getString("user_id")?.toLongOrNull() ?: 0L,
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.navigate(it) },
            )
        }
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
        composable(Routes.NOTICES) {
            NoticeRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.NOTICE_INTERACT) {
            NoticeRoute(
                onBack = { navController.popBackStack() },
                initialCategory = NoticeCategory.INTERACT,
            )
        }
        composable(Routes.DAILY_TASK) {
            DailyTaskRoute(
                onBack = { navController.popBackStack() },
                onOpenEditProfile = { navController.navigate(Routes.EDIT_PROFILE) },
                // 「去认识」要回到发现页找人说两句。发现是底部导航的一个 Tab，
                // 它的选中状态在主界面的 ViewModel 里，从这儿改不了 ——
                // 所以整个主界面重建一次（MainViewModel 默认就停在发现 Tab）。
                onOpenDiscover = {
                    navController.navigate(Routes.MAIN) {
                        popUpTo(Routes.MAIN) { inclusive = true }
                        launchSingleTop = true
                    }
                },
            )
        }
        composable(Routes.VIP) {
            VipCenterRoute(
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.navigate(it) },
            )
        }
        composable(Routes.COIN_WALLET) {
            WalletRoute(
                onBack = { navController.popBackStack() },
                onOpenRecharge = { navController.navigate(Routes.RECHARGE) },
                onOpenLogs = { navController.navigate(Routes.COIN_LOGS) },
            )
        }
        composable(Routes.RECHARGE) {
            RechargeRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.COIN_LOGS) {
            CoinLogsRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.EDIT_PROFILE) {
            EditProfileRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.SETTINGS) {
            SettingsRoute(
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.navigate(it) },
            )
        }
        composable(Routes.BLOCK_LIST) {
            BlockListRoute(onBack = { navController.popBackStack() })
        }
        placeholder(navController, Routes.VOICE_HALL, "语音大厅（P16）")
        placeholder(navController, Routes.CALL_CONFIRM, "拨打前价格确认（P17）")
        placeholder(navController, Routes.CALL_WAITING, "等待接通（P18）")
        placeholder(navController, Routes.VOICE_CALL, "一对一语音通话（P19）")
        placeholder(navController, Routes.VIDEO_CALL, "一对一视频通话（P20）")
        placeholder(navController, Routes.CALL_BILL, "结束通话账单（P21）")
        composable(Routes.GIFT_PANEL) { entry ->
            GiftPanelRoute(
                userId = entry.arguments?.getString("user_id")?.toLongOrNull() ?: 0L,
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.navigate(it) },
            )
        }
        placeholder(navController, Routes.CALL_RECORDS, "通话记录（P25）")
        composable(Routes.INCOME) {
            IncomeRoute(
                onBack = { navController.popBackStack() },
                onOpenWithdraw = { navController.navigate(Routes.WITHDRAW) },
                onOpenLevel = { navController.navigate(Routes.LEVEL_SETTING) },
            )
        }
        composable(Routes.LEVEL_SETTING) {
            IncomeLevelRoute(
                onBack = { navController.popBackStack() },
                onOpenRealName = { navController.navigate(Routes.REAL_NAME) },
            )
        }
        composable(Routes.WITHDRAW) {
            WithdrawRoute(
                onBack = { navController.popBackStack() },
                onOpenRealName = { navController.navigate(Routes.REAL_NAME) },
            )
        }
        composable(Routes.REAL_NAME) {
            RealNameRoute(onBack = { navController.popBackStack() })
        }
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
