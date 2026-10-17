package com.qichuang.changliao.core.navigation

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.navigationBars
import androidx.compose.foundation.layout.windowInsetsPadding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.navigation.NavBackStackEntry
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
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.feature.call.CallBillRoute
import com.qichuang.changliao.feature.call.CallConfirmRoute
import com.qichuang.changliao.feature.call.CallInProgressRoute
import com.qichuang.changliao.feature.call.CallRecordsRoute
import com.qichuang.changliao.feature.call.CallWaitingRoute
import com.qichuang.changliao.feature.chat.ChatRoute
import com.qichuang.changliao.feature.gift.GiftPanelRoute
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
 * `docs/01` 第 5 节那 31 个页面**已经全部实现**，所以这里没有任何「开发中」占位了
 * （P8 的三个占位在 T8.2 换成了真页面，`PlaceholderPage` 随之删掉）。
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
                onRegister = { navController.open(Routes.REGISTER) },
                onOpenAgreement = { type -> navController.open(Routes.agreement(type)) },
            )
        }

        composable(Routes.REGISTER) {
            RegisterRoute(
                onGoMain = { navController.navigate(Routes.MAIN) { popUpTo(Routes.LOGIN) { inclusive = true } } },
                onBack = { navController.popBackStack() },
            )
        }

        composable(Routes.MAIN) {
            MainScreen(onOpen = { route -> navController.open(route) })
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
                onChat = { navController.open(Routes.chat(it)) },
                onOpenGift = { id, name -> navController.open(Routes.giftPanel(id, name)) },
            )
        }

        composable(Routes.CHAT) { entry ->
            ChatRoute(
                userId = entry.arguments?.getString("user_id")?.toLongOrNull() ?: 0L,
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.FILTER) {
            FilterRoute(onBack = { navController.popBackStack() })
        }
        composable(Routes.VISITORS) {
            VisitorsRoute(
                onBack = { navController.popBackStack() },
                onOpenUser = { navController.open(Routes.userDetail(it)) },
                onOpenVip = { navController.open(Routes.VIP) },
            )
        }
        composable(Routes.LIKED_ME) {
            FansRoute(
                onBack = { navController.popBackStack() },
                onOpenUser = { navController.open(Routes.userDetail(it)) },
                onOpenVip = { navController.open(Routes.VIP) },
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
                onOpenEditProfile = { navController.open(Routes.EDIT_PROFILE) },
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
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.COIN_WALLET) {
            WalletRoute(
                onBack = { navController.popBackStack() },
                onOpenRecharge = { navController.open(Routes.RECHARGE) },
                onOpenLogs = { navController.open(Routes.COIN_LOGS) },
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
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.BLOCK_LIST) {
            BlockListRoute(onBack = { navController.popBackStack() })
        }
        // P02/P16 挂在主界面的 Tab 导航树里（底部导航要一直在），见 MainScreen
        composable(Routes.CALL_CONFIRM) { entry ->
            CallConfirmRoute(
                userId = entry.userId(),
                kind = entry.callKind(),
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.CALL_WAITING) { entry ->
            CallWaitingRoute(
                userId = entry.userId(),
                kind = entry.callKind(),
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.VOICE_CALL) { entry ->
            CallInProgressRoute(
                userId = entry.userId(),
                kind = CallKind.VOICE,
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.VIDEO_CALL) { entry ->
            CallInProgressRoute(
                userId = entry.userId(),
                kind = CallKind.VIDEO,
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.CALL_BILL) { entry ->
            CallBillRoute(
                userId = entry.userId(),
                kind = entry.callKind(),
                seconds = entry.arguments?.getString("seconds")?.toIntOrNull() ?: 0,
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.CALL_RECORDS) {
            CallRecordsRoute(
                onBack = { navController.popBackStack() },
                onOpenDiscover = {
                    navController.navigate(Routes.MAIN) {
                        popUpTo(Routes.MAIN) { inclusive = true }
                        launchSingleTop = true
                    }
                },
            )
        }
        composable(Routes.GIFT_PANEL) { entry ->
            GiftPanelRoute(
                userId = entry.arguments?.getString("user_id")?.toLongOrNull() ?: 0L,
                peerName = entry.arguments?.getString("name").orEmpty(),
                onBack = { navController.popBackStack() },
                onOpenRoute = { navController.open(it) },
            )
        }
        composable(Routes.INCOME) {
            IncomeRoute(
                onBack = { navController.popBackStack() },
                onOpenWithdraw = { navController.open(Routes.WITHDRAW) },
                onOpenLevel = { navController.open(Routes.LEVEL_SETTING) },
            )
        }
        composable(Routes.LEVEL_SETTING) {
            IncomeLevelRoute(
                onBack = { navController.popBackStack() },
                onOpenRealName = { navController.open(Routes.REAL_NAME) },
            )
        }
        composable(Routes.WITHDRAW) {
            WithdrawRoute(
                onBack = { navController.popBackStack() },
                onOpenRealName = { navController.open(Routes.REAL_NAME) },
            )
        }
        composable(Routes.REAL_NAME) {
            RealNameRoute(onBack = { navController.popBackStack() })
        }
    }
}

/**
 * 页面跳转统一走这里。
 *
 * `launchSingleTop = true`：**连点同一个入口不会把页面压两层**（T9.7 实测发现的 ——
 * 快速点两下发现页的卡片，要按两次返回才退得出去）。
 * 只对「同一个目的地已经在栈顶」生效，正常的前进/返回不受影响。
 */
private fun NavHostController.open(route: String) {
    navigate(route) { launchSingleTop = true }
}

/** 路由参数里的 `user_id`（P17~P21 都要）。 */
private fun NavBackStackEntry.userId(): Long =
    arguments?.getString("user_id")?.toLongOrNull() ?: 0L

/** 路由参数里的 `kind`（`voice` / `video`），认不出来按语音算。 */
private fun NavBackStackEntry.callKind(): CallKind =
    CallKind.fromApiValue(arguments?.getString("kind"))
