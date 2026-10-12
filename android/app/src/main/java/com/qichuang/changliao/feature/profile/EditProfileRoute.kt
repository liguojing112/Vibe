package com.qichuang.changliao.feature.profile

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** 把 [EditProfileViewModel] 和 [EditProfileScreen] 连起来。 */
@Composable
fun EditProfileRoute(
    onBack: () -> Unit,
    viewModel: EditProfileViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    val actions = remember(viewModel) {
        EditProfileActions(
            onNicknameChange = viewModel::onNicknameChange,
            onCityChange = viewModel::onCityChange,
            onHeightChange = viewModel::onHeightChange,
            onJobChange = viewModel::onJobChange,
            onEducationChange = viewModel::onEducationChange,
            onDatingPurposeChange = viewModel::onDatingPurposeChange,
            onBioChange = viewModel::onBioChange,
            onTagsChange = viewModel::onTagNamesChanged,
            onBirthdaySelected = viewModel::onBirthdaySelected,
            onAvatarPicked = viewModel::onAvatarPicked,
            onPhotoPicked = viewModel::onPhotoPicked,
            onPhotoRemoved = viewModel::onPhotoRemoved,
            onPhotoMoved = viewModel::onPhotoMoved,
            onRetry = viewModel::load,
            onSave = viewModel::save,
            onToastShown = viewModel::onToastShown,
        )
    }

    EditProfileScreen(state = state, actions = actions, onBack = onBack)
}
