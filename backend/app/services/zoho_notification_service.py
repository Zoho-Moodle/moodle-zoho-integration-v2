"""
DEPRECATED - This module has been removed.

Zoho CRM Notification Channels did not work reliably with Custom Modules (BTEC_*).
They have been replaced by Workflow Rules, which are permanent and work with all modules.

Use app.services.zoho_workflow_service.ZohoWorkflowService instead.
"""

raise ImportError(
    "zoho_notification_service has been removed. "
    "Use app.services.zoho_workflow_service.ZohoWorkflowService instead."
)
