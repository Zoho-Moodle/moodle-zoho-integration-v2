"""
Moodle API Client Implementation

This module provides a client for interacting with Moodle REST API.
Supports user management and course enrolments.

Moodle Documentation:
- https://moodle.org/plugins/webservices/
- core_user_create_users
- core_user_get_users_by_field
- core_course_get_courses_by_field
- enrol_manual_enrol_users
"""

import logging
from typing import Any, Dict, Optional
import requests
from app.core.config import settings

logger = logging.getLogger(__name__)


class MoodleClient:
    def __init__(self, base_url: Optional[str] = None, token: Optional[str] = None, enabled: bool = True):
        """
        Initialize Moodle API client
        
        Args:
            base_url: Moodle installation URL (e.g., https://moodle.example.com)
            token: API token for authentication
            enabled: Whether to actually call Moodle API (for testing)
        """
        self.base_url = base_url or settings.MOODLE_BASE_URL or ""
        self.token = token or settings.MOODLE_TOKEN or ""
        self.enabled = enabled and bool(self.base_url and self.token)
        
        if self.enabled:
            self.session = requests.Session()
            self.base_url = self.base_url.rstrip("/")
        else:
            self.session = None
            logger.info("Moodle client disabled (no base_url or token configured)")

    def _call_api(self, function: str, params: Dict[str, Any]) -> Dict[str, Any]:
        """
        Call Moodle web service function
        
        Args:
            function: Moodle function name (e.g., core_user_create_users)
            params: Function parameters
            
        Returns:
            API response
            
        Raises:
            Exception: If API call fails
        """
        if not self.enabled:
            logger.debug(f"Moodle disabled: {function} would be called with {params}")
            return {"id": 999}

        url = f"{self.base_url}/webservice/rest/server.php"
        payload = {
            "wstoken": self.token,
            "wsfunction": function,
            "moodlewsrestformat": "json",
            **params
        }
        
        try:
            response = self.session.get(url, params=payload, timeout=10)
            response.raise_for_status()
            data = response.json()
            
            if isinstance(data, dict) and "exception" in data:
                raise Exception(f"Moodle API error: {data.get('message', 'Unknown error')}")
                
            return data
        except requests.RequestException as e:
            logger.error(f"Moodle API call failed: {e}")
            raise

    def get_user_by_username(self, username: str) -> Optional[Dict[str, Any]]:
        """
        Get Moodle user by username
        
        Args:
            username: Moodle username
            
        Returns:
            User data {"id": int, "username": str, ...} or None
        """
        if not self.enabled:
            logger.debug(f"TODO: Get Moodle user by username: {username}")
            return {"id": 999, "username": username}

        try:
            result = self._call_api("core_user_get_users_by_field", {
                "field": "username",
                "values[0]": username
            })
            
            if isinstance(result, list) and len(result) > 0:
                return result[0]
            return None
        except Exception as e:
            logger.error(f"Failed to get user {username}: {e}")
            return None

    def create_user(self, email: str, firstname: str, lastname: str, username: str) -> Optional[Dict[str, Any]]:
        """
        Create a new Moodle user
        
        Args:
            email: User email
            firstname: First name
            lastname: Last name
            username: Username (must be unique)
            
        Returns:
            Created user data {"id": int, "username": str, ...} or None
        """
        if not self.enabled:
            logger.debug(f"TODO: Create Moodle user: {username} ({email})")
            return {"id": 999, "username": username, "email": email}

        try:
            # First check if user already exists
            existing = self.get_user_by_username(username)
            if existing:
                return existing

            # Create new user
            result = self._call_api("core_user_create_users", {
                "users[0][username]": username,
                "users[0][email]": email,
                "users[0][firstname]": firstname,
                "users[0][lastname]": lastname,
                "users[0][auth]": "manual",
                "users[0][password]": "TempPassword123!",  # User must change on first login
            })
            
            if isinstance(result, list) and len(result) > 0:
                return result[0]
            return None
        except Exception as e:
            logger.error(f"Failed to create user {username}: {e}")
            return None

    def get_course_by_idnumber(self, idnumber: str) -> Optional[Dict[str, Any]]:
        """
        Get Moodle course by idnumber
        
        Args:
            idnumber: Course idnumber (from moodle_class_id field)
            
        Returns:
            Course data {"id": int, "idnumber": str, ...} or None
        """
        if not self.enabled:
            logger.debug(f"TODO: Get Moodle course by idnumber: {idnumber}")
            return {"id": 999, "idnumber": idnumber}

        try:
            result = self._call_api("core_course_get_courses_by_field", {
                "field": "idnumber",
                "value": idnumber
            })
            
            if isinstance(result, dict) and "courses" in result and len(result["courses"]) > 0:
                return result["courses"][0]
            return None
        except Exception as e:
            logger.error(f"Failed to get course {idnumber}: {e}")
            return None

    def enrol_user(self, course_id: int, user_id: int, role_id: int = 5) -> Optional[Dict[str, Any]]:
        """
        Enrol a user in a course
        
        Args:
            course_id: Moodle course ID
            user_id: Moodle user ID
            role_id: Moodle role ID (5=student, 4=teacher, 3=course creator)
            
        Returns:
            Enrollment confirmation or None
        """
        if not self.enabled:
            logger.debug(f"TODO: Enrol user {user_id} in course {course_id} as role {role_id}")
            return {"id": 999, "userid": user_id, "courseid": course_id}

        try:
            # Use manual enrol plugin (most common)
            result = self._call_api("enrol_manual_enrol_users", {
                "enrolments[0][userid]": user_id,
                "enrolments[0][courseid]": course_id,
                "enrolments[0][roleid]": role_id,
            })
            
            # API returns empty on success
            return {"id": 999, "userid": user_id, "courseid": course_id}
        except Exception as e:
            logger.error(f"Failed to enrol user {user_id} in course {course_id}: {e}")
            return None


# Backward compatibility: also expose as MoodleUsersClient
MoodleUsersClient = MoodleClient
