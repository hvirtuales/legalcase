<?php

return [
    // Authentication
    'mustBeLoggedIn' => 'You must be logged in to access this page',
    'invalidCredentials' => 'Invalid username or password',
    'accountLocked' => 'Your account has been locked due to multiple failed attempts',
    'accountInactive' => 'Your account is inactive. Please contact the administrator',
    'insufficientPermissions' => 'You do not have sufficient permissions to perform this action',
    'registrationSuccess' => 'Registration successful. You can now log in',
    'registrationFailed' => 'Failed to register user. Please try again',
    'publicRegistrationDisabled' => 'Public registration is disabled. Contact the administrator to create an account',
    'logoutSuccess' => 'You have successfully logged out',
    'invalid2FACode' => 'Invalid verification code',
    'recoveryCodeUsed' => 'You have used a recovery code. We recommend regenerating your codes',
    'login' => 'Login',
    'loginSubtitle' => 'Enter your credentials to continue',
    'usernameOrEmail' => 'Username or Email',
    'enterUsernameOrEmail' => 'Enter your username or email',
    'enterPassword' => 'Enter your password',
    'rememberMe' => 'Remember me',
    'forgotPassword' => 'Forgot your password?',
    'noAccount' => 'Don\'t have an account?',
    'registerHere' => 'Register here',
    'register' => 'Register',
    'createAccount' => 'Create Account',
    'registerSubtitle' => 'Complete the form to create your account',
    'alreadyHaveAccount' => 'Already have an account?',
    'loginHere' => 'Login here',

    // General
    'welcome' => 'Welcome',
    'dashboard' => 'Dashboard',
    'users' => 'Users',
    'roles' => 'Roles',
    'permissions' => 'Permissions',
    'settings' => 'Settings',
    'auditLogs' => 'Audit Logs',
    'auditLogDetail' => 'Audit Log Detail',
    'auditStatistics' => 'Audit Statistics',

    // Actions
    'create' => 'Create',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'back' => 'Back',
    'search' => 'Search',
    'filter' => 'Filter',
    'export' => 'Export',
    'import' => 'Import',

    // Messages
    'createSuccess' => 'Record created successfully',
    'createFailed' => 'Failed to create record',
    'updateSuccess' => 'Record updated successfully',
    'updateFailed' => 'Failed to update record',
    'deleteSuccess' => 'Record deleted successfully',
    'deleteFailed' => 'Failed to delete record',
    'notFound' => 'Record not found',
    'settingsUpdated' => 'Settings updated successfully',
    'permissionsUpdated' => 'Permissions updated successfully',
    'rolesAssigned' => 'Roles assigned successfully',

    // Validation
    'required' => 'This field is required',
    'minLength' => 'Must be at least {0} characters',
    'maxLength' => 'Cannot be more than {0} characters',
    'validEmail' => 'Must be a valid email',
    'unique' => 'This value already exists in the system',
    'matches' => 'Fields do not match',

    // User Management
    'createUser' => 'Create User',
    'editUser' => 'Edit User',
    'assignRoles' => 'Assign Roles',
    'cannotDeleteSelf' => 'You cannot delete your own account',
    'roleHasUsers' => 'Cannot delete role because it has assigned users',

    // Role Management
    'createRole' => 'Create Role',
    'editRole' => 'Edit Role',
    'rolePermissions' => 'Role Permissions',

    // Permission Management
    'createPermission' => 'Create Permission',
    'editPermission' => 'Edit Permission',

    // File Upload
    'invalidFile' => 'Invalid file',
    'invalidFileType' => 'File type not allowed',
    'uploadSuccess' => 'File uploaded successfully',
    'uploadFailed' => 'Failed to upload file',
];
