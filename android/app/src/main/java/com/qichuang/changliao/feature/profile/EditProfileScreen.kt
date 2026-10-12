package com.qichuang.changliao.feature.profile

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClDatePickerDialog
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPhotoGrid
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSelectField
import com.qichuang.changliao.core.designsystem.component.ClSelectOption
import com.qichuang.changliao.core.designsystem.component.ClTag
import com.qichuang.changliao.core.designsystem.component.ClTagKind
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import java.time.LocalDate

/** 页面里所有会改状态的动作，集中成一个对象，省得 Screen 收十几个回调参数。 */
data class EditProfileActions(
    val onNicknameChange: (String) -> Unit,
    val onCityChange: (String) -> Unit,
    val onHeightChange: (String) -> Unit,
    val onJobChange: (String) -> Unit,
    val onEducationChange: (String) -> Unit,
    val onDatingPurposeChange: (String) -> Unit,
    val onBioChange: (String) -> Unit,
    val onTagsChange: (Set<String>) -> Unit,
    val onBirthdaySelected: (Long) -> Unit,
    val onAvatarPicked: (Uri) -> Unit,
    val onPhotoPicked: (Uri) -> Unit,
    val onPhotoRemoved: (Int) -> Unit,
    val onPhotoMoved: (Int, Int) -> Unit,
    val onRetry: () -> Unit,
    val onSave: () -> Unit,
    val onToastShown: () -> Unit,
)

/**
 * 编辑资料页（docs/01 P14，设计图 14）。
 *
 * 头像、昵称、自我介绍、相册保存后**进入审核**，页面打「审核中」角标；
 * 其余字段（生日、城市、身高、职业、学历、交友期待、标签）保存即生效。
 */
@Composable
fun EditProfileScreen(
    state: EditProfileUiState,
    actions: EditProfileActions,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var picker by remember { mutableStateOf<ProfilePicker?>(null) }
    var showBirthdayPicker by remember { mutableStateOf(false) }

    val photoPicker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri: Uri? -> uri?.let(actions.onPhotoPicked) }

    val avatarPicker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickVisualMedia(),
    ) { uri: Uri? -> uri?.let(actions.onAvatarPicked) }

    ClScaffold(
        modifier = modifier,
        topBar = {
            ClTopBar(
                title = "编辑资料",
                onBack = onBack,
                action = {
                    ClTextButton(
                        text = "保存",
                        enabled = state.canSave,
                        onClick = actions.onSave,
                    )
                },
            )
        },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                actions.onToastShown()
            }
        }

        when {
            state.loading -> ClLoading()

            state.loadError != null -> ClErrorRetry(
                message = state.loadError,
                onRetry = actions.onRetry,
            )

            else -> EditProfileForm(
                state = state,
                actions = actions,
                onPickAvatar = {
                    avatarPicker.launch(
                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly),
                    )
                },
                onPickPhoto = {
                    photoPicker.launch(
                        PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly),
                    )
                },
                onPickBirthday = { showBirthdayPicker = true },
                onPickOption = { picker = it },
            )
        }

        if (showBirthdayPicker) {
            ClDatePickerDialog(
                title = "选择生日",
                initialUtcMillis = com.qichuang.changliao.core.common.TimeFormat
                    .dateToUtcMillis(state.birthday),
                yearRange = (LocalDate.now().year - 100)..(LocalDate.now().year - EditProfileViewModel.MIN_AGE),
                onConfirm = {
                    actions.onBirthdaySelected(it)
                    showBirthdayPicker = false
                },
                onDismiss = { showBirthdayPicker = false },
            )
        }

        picker?.let { target ->
            ClBottomSheet(title = pickerTitle(target), onClose = { picker = null }) {
                SingleChoiceList(
                    options = pickerOptions(target, state),
                    selected = pickerSelected(target, state),
                    onSelect = { value ->
                        when (target) {
                            ProfilePicker.City -> actions.onCityChange(value)
                            ProfilePicker.Education -> actions.onEducationChange(value)
                            ProfilePicker.DatingPurpose -> actions.onDatingPurposeChange(value)
                        }
                        picker = null
                    },
                )
            }
        }
    }
}

@Composable
private fun EditProfileForm(
    state: EditProfileUiState,
    actions: EditProfileActions,
    onPickAvatar: () -> Unit,
    onPickPhoto: () -> Unit,
    onPickBirthday: () -> Unit,
    onPickOption: (ProfilePicker) -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = ClTheme.spacing.l),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        if (state.isFemale) {
            ClNoticeCard(
                title = "请使用本人真实照片和资料",
                content = "虚假资料将被封号",
                highlight = true,
            )
        }

        AvatarPicker(
            avatarUrl = state.avatarUrl,
            auditing = state.isAuditing(UserProfile.FIELD_AVATAR),
            uploading = state.uploading,
            onClick = onPickAvatar,
        )

        ClTextField(
            label = "昵称",
            value = state.nickname,
            onValueChange = actions.onNicknameChange,
            placeholder = "2~12 个字",
            error = state.nicknameError,
            maxLength = 12,
            trailingIcon = auditingTag(state.isAuditing(UserProfile.FIELD_NICKNAME)),
        )

        ClSelectField(
            label = "生日",
            value = state.birthday,
            placeholder = "请选择（需满 ${EditProfileViewModel.MIN_AGE} 岁）",
            error = state.birthdayError,
            onClick = onPickBirthday,
        )

        ClSelectField(
            label = "所在城市",
            value = state.city,
            placeholder = "请选择",
            onClick = { onPickOption(ProfilePicker.City) },
        )

        ClTextField(
            label = "身高（cm）",
            value = state.height,
            onValueChange = actions.onHeightChange,
            placeholder = "选填",
            error = state.heightError,
            keyboardType = KeyboardType.Number,
            maxLength = 3,
        )

        ClTextField(
            label = "职业",
            value = state.job,
            onValueChange = actions.onJobChange,
            placeholder = "选填",
            maxLength = 32,
        )

        ClSelectField(
            label = "学历",
            value = state.education,
            placeholder = "请选择",
            onClick = { onPickOption(ProfilePicker.Education) },
        )

        ClSelectField(
            label = "交友期待",
            value = state.datingPurpose,
            placeholder = "请选择",
            onClick = { onPickOption(ProfilePicker.DatingPurpose) },
        )

        ClTextField(
            label = "自我介绍",
            value = state.bio,
            onValueChange = actions.onBioChange,
            placeholder = "介绍一下自己，最多 ${EditProfileViewModel.MAX_BIO_LENGTH} 字",
            error = state.bioError,
            singleLine = false,
            maxLength = EditProfileViewModel.MAX_BIO_LENGTH,
            trailingIcon = auditingTag(state.isAuditing(UserProfile.FIELD_BIO)),
        )

        Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs)) {
            Text(
                text = "兴趣标签（最多 ${EditProfileViewModel.MAX_TAGS} 个）",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.inkSecondary,
            )
            ClChipGroup(
                options = state.tagOptions.map { it.name },
                selected = state.tagOptions.filter { it.id in state.tags }.map { it.name }.toSet(),
                onSelectionChange = actions.onTagsChange,
                multi = true,
            )
            state.tagsError?.let {
                Text(
                    text = it,
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.danger,
                )
            }
        }

        Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text(
                    text = "相册（最多 ${EditProfileViewModel.MAX_PHOTOS} 张）",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.inkSecondary,
                    modifier = Modifier.weight(1f),
                )
                auditingTag(state.isAuditing(UserProfile.FIELD_PHOTO))?.invoke()
                Text(
                    text = "长按可拖动排序",
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                )
            }
            ClPhotoGrid(
                urls = state.photos,
                editable = true,
                maxCount = EditProfileViewModel.MAX_PHOTOS,
                onAdd = onPickPhoto,
                onRemove = actions.onPhotoRemoved,
                onMove = actions.onPhotoMoved,
            )
        }

        Spacer(modifier = Modifier.height(ClTheme.spacing.s))

        ClPrimaryButton(
            text = "保存资料",
            loading = state.submitting,
            enabled = state.canSave,
            onClick = actions.onSave,
        )

        Spacer(modifier = Modifier.height(ClTheme.spacing.xxxl))
    }
}

@Composable
private fun AvatarPicker(
    avatarUrl: String,
    auditing: Boolean,
    uploading: Boolean,
    onClick: () -> Unit,
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        Box(modifier = Modifier.clickable(onClick = onClick)) {
            ClAvatar(
                url = avatarUrl.ifBlank { null },
                size = ClTheme.size.avatarL,
                contentDescription = "更换头像",
            )
        }
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
        ) {
            Text(
                text = if (uploading) "上传中…" else "点击头像换一张",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
            )
            if (auditing) {
                ClTag(text = "审核中", kind = ClTagKind.Gold)
            }
        }
    }
}

/** 「审核中」小角标，[visible] = false 时什么都不画。 */
private fun auditingTag(visible: Boolean): (@Composable () -> Unit)? = if (visible) {
    { ClTag(text = "审核中", kind = ClTagKind.Gold) }
} else {
    null
}

@Composable
private fun SingleChoiceList(
    options: List<String>,
    selected: String,
    onSelect: (String) -> Unit,
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        options.forEach { option ->
            ClSelectOption(
                text = option,
                selected = option == selected,
                onClick = { onSelect(option) },
            )
        }
    }
}

private fun pickerTitle(target: ProfilePicker): String = when (target) {
    ProfilePicker.City -> "选择城市"
    ProfilePicker.Education -> "选择学历"
    ProfilePicker.DatingPurpose -> "选择交友期待"
}

private fun pickerOptions(target: ProfilePicker, state: EditProfileUiState): List<String> = when (target) {
    ProfilePicker.City -> state.cities
    ProfilePicker.Education -> EDUCATION_OPTIONS
    ProfilePicker.DatingPurpose -> DATING_PURPOSE_OPTIONS
}

private fun pickerSelected(target: ProfilePicker, state: EditProfileUiState): String = when (target) {
    ProfilePicker.City -> state.city
    ProfilePicker.Education -> state.education
    ProfilePicker.DatingPurpose -> state.datingPurpose
}
