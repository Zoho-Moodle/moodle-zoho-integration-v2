# Zoho CRM API Fields Complete Reference
# Backend Sync Mapping Documentation

> **Purpose:** Complete reference of all Zoho CRM API fields for BTEC system integration  
> **Date:** January 26, 2026  
> **Source:** Zoho CRM API Names documentation

<div dir="rtl">

## 📋 جدول المحتويات

- [BTEC Students](#btec-students) - الطلاب
- [BTEC Programs](#btec-programs) - البرامج
- [BTEC Units](#btec-units) - الوحدات
- [BTEC Classes](#btec-classes) - الصفوف
- [BTEC Enrollments](#btec-enrollments) - التسجيلات في الصفوف
- [BTEC Grades](#btec-grades) - الدرجات
- [BTEC Teachers](#btec-teachers) - المعلمين
- [BTEC Registrations](#btec-registrations) - تسجيلات البرامج
- [BTEC Payments](#btec-payments) - الدفعات
- [Moodle Integration Fields](#moodle-integration-fields) - حقول Moodle المستخدمة

---

</div>

## 1. BTEC Students

**Module:** `BTEC_Students`  
**Purpose:** Student records with academic and personal information

| Field Label | API Name | Data Type | Custom | Moodle Used | Notes |
|------------|----------|-----------|--------|-------------|-------|
| Academic Email | Academic_Email | Email (Unique) | ✓ | ✅ | Primary email from Moodle |
| Academic Password | Academic_Password | Single Line | ✓ | ❌ | Not synced to Moodle |
| Academic Program | Academic_Program | Pick List | ✓ | ❌ | |
| Accounting Principles | Accounting_Principles | Single Line | ✓ | ❌ | |
| Address | Address | Multi Line (Small) | ✓ | ❌ | |
| Advanced Programming | Advanced_Programming | Single Line | ✓ | ❌ | |
| Allow Enrollment | Allow_Enrollment | Single Line | ✓ | ❌ | |
| Amount Transferred | Amount_Transferred | Currency | ✓ | ❌ | |
| Animal Conservation | Animal_Conservation | Single Line | ✓ | ❌ | |
| Application Development | Application_Development | Single Line | ✓ | ❌ | |
| Applications of Inorganic Chemistry | Applications_of_Inorganic_Chemistry | Single Line | ✓ | ❌ | |
| Applications of Organic Chemistry | Applications_of_Organic_Chemistry | Single Line | ✓ | ❌ | |
| Applications of Physical Chemistry | Applications_of_Physical_Chemistry | Single Line | ✓ | ❌ | |
| Applied Cryptography in the Cloud | Applied_Cryptography_in_the_Cloud | Single Line | ✓ | ❌ | |
| Applied Programming and Design Principles | Applied_Programming_and_Design_Principles | Single Line | ✓ | ❌ | |
| Astronomy and Space Science | Astronomy_and_Space_Science | Single Line | ✓ | ❌ | |
| Bank Holder Name | Bank_Holder_Name | Single Line | ✓ | ❌ | |
| Bank Name | Bank_Name | Single Line | ✓ | ❌ | |
| Biological Molecules and Metabolic Pathways | Biological_Molecules_and_Metabolic_Pathways | Single Line | ✓ | ❌ | |
| Biomedical Science | Biomedical_Science | Single Line | ✓ | ❌ | |
| Birth Date | Birth_Date | Date | ✓ | ❌ | |
| Birth Place | Birth_Place | Single Line | ✓ | ❌ | |
| Branch ID | Branch_ID | Single Line | ✓ | ❌ | |
| BTEC RegNum L3 | BTEC_RegNum_L3 | Single Line | ✓ | ❌ | |
| BTEC RegNum L5 | BTEC_RegNum_L5 | Single Line | ✓ | ❌ | |
| BTEC RegNum L7 | BTEC_RegNum_L7 | Single Line | ✓ | ❌ | |
| BTEC RegNum SRM | BTEC_Registration_Number | Single Line | ✓ | ❌ | |
| BTEC Student Image | Record_Image | BTEC Student Image | ✓ | ❌ | |
| BTEC Student Owner | Owner | Lookup | ✓ | ❌ | |
| Business and the Business Environment | Business_and_the_Business_Environment | Single Line | ✓ | ❌ | |
| Business Data Analytics and Insights | Business_Data_Analytics_and_Insights | Single Line | ✓ | ❌ | |
| Business Information Technology Systems | Business_Information_Technology_Systems | Single Line | ✓ | ❌ | |
| Business Intelligence | Business_Intelligence | Single Line | ✓ | ❌ | |
| Business Law | Business_Law | Single Line | ✓ | ❌ | |
| Business Process Support | Business_Process_Support | Single Line | ✓ | ❌ | |
| Business Strategy | Business_Strategy | Single Line | ✓ | ❌ | |
| Certificate Status | Certificate_Status | Single Line | ✓ | ❌ | |
| City | City | Single Line | ✓ | ✅ | City from Moodle |
| Connected To | Connected_To_s | MultiModuleLookup | - | ❌ | |
| Created By | Created_By | Single Line | - | ❌ | |
| Currency | Currency | Pick List | - | ❌ | |
| Email | Email | Email | - | ❌ | |
| Email Opt Out | Email_Opt_Out | Boolean | - | ❌ | |
| Exchange Rate | Exchange_Rate | Decimal | - | ❌ | |
| Financial Principles and Techniques | Financial_Principles_and_Techniques | Single Line | ✓ | ❌ | |
| Financial Reporting | Financial_Reporting | Single Line | ✓ | ❌ | |
| First Name | First_Name | Single Line | - | ✅ | From Moodle firstname |
| Forensics | Forensics | Single Line | ✓ | ❌ | |
| Freeze Date | Freeze_Date | Date | ✓ | ❌ | |
| Freeze Reason | Freeze_Reason | Multi Line (Small) | ✓ | ❌ | |
| Frist Name (Native) | Frist_Name_Native | Single Line | ✓ | ❌ | |
| Functional Physiology of Human Body Systems | Functional_Physiology_of_Human_Body_Systems | Single Line | ✓ | ❌ | |
| Further Engineering Mathematics | Further_Engineering_Mathematics | Single Line | ✓ | ❌ | |
| Gender | Gender | Pick List | ✓ | ❌ | |
| Genetics and Genetic Engineering | Genetics_and_Genetic_Engineering | Single Line | ✓ | ❌ | |
| Global Business Environment | Global_Business_Environment | Single Line | ✓ | ❌ | |
| Human Regulation and Reproduction | Human_Regulation_and_Reproduction | Single Line | ✓ | ❌ | |
| Human Resource Management | Human_Resource_Management | Single Line | ✓ | ❌ | |
| Human Resources – Value and Contribution | Human_Resources_Value_and_Contribution | Single Line | ✓ | ❌ | |
| ID Type | ID_Type | Pick List | ✓ | ❌ | |
| Index | Index | Number | ✓ | ❌ | |
| Information Security Management | Information_Security_Management | Single Line | ✓ | ❌ | |
| Intake | Intake | Single Line | ✓ | ❌ | |
| Integrated Marketing Communications | Integrated_Marketing_Communications | Single Line | ✓ | ❌ | |
| International Marketing | International_Marketing | Single Line | ✓ | ❌ | |
| Investigative Project Skills | Investigative_Project_Skills | Single Line | ✓ | ❌ | |
| isEnrolled | isEnrolled | Single Line | ✓ | ❌ | |
| isFlowTriggerWelcome | isFlowTriggerWelcome | Single Line | ✓ | ❌ | |
| isOfficialLetter | isOfficialLetter | Single Line | ✓ | ❌ | |
| isStudentCard | isStudentCard | Single Line | ✓ | ❌ | |
| isStudentLetter | isStudentLetter | Single Line | ✓ | ❌ | |
| isSubmitted | isSubmitted | Single Line | ✓ | ❌ | |
| isTranscript | isTranscript | Single Line | ✓ | ❌ | |
| isWelcomed | isWelcomed | Single Line | ✓ | ❌ | |
| IT - E-Commerce & Strategy | IT_E_Commerce_Strategy | Single Line | ✓ | ❌ | |
| IT - Business Information Technology Systems | IT_Business_Information_Technology_Systems | Single Line | ✓ | ❌ | |
| IT - Business Intelligence | IT_Business_Intelligence | Single Line | ✓ | ❌ | |
| L7 - Strategic Human Resource Management | L7_Strategic_Human_Resource_Management | Single Line | ✓ | ❌ | |
| Last Educational Level | Last_Educational_Level | Pick List | ✓ | ❌ | |
| Last Name | Last_Name | Single Line | - | ✅ | From Moodle lastname |
| Last Name (Native) | Last_Name_Native | Single Line | ✓ | ❌ | |
| Last Sync Date (profile info) | Last_Sync_Date | DateTime | ✓ | ✅ | Updated after Moodle sync |
| Leadership and Management | Leadership_and_Management | Single Line | ✓ | ❌ | |
| Leading E-strategy | Leading_E_strategy | Single Line | ✓ | ❌ | |
| Letter Type | Letter_Type | Pick List | ✓ | ❌ | |
| M365_Created | M365_Created | Single Line | ✓ | ❌ | |
| Major | Major | Pick List | ✓ | ❌ | |
| Management Accounting | Management_Accounting | Single Line | ✓ | ❌ | |
| Managing a Successful Business Project | Managing_a_Successful_Business_Project | Single Line | ✓ | ❌ | |
| Managing Successful Computing Projects | Managing_Successful_Computing_Projects | Single Line | ✓ | ❌ | |
| manual enrollment | manual_enrollment | Single Line | ✓ | ❌ | |
| manual search | manual_search | Single Line | ✓ | ❌ | |
| manual search 2 | manual_search_2 | Single Line | ✓ | ❌ | |
| Marketing Insights and Analytics | Marketing_Insights_and_Analytics | Single Line | ✓ | ❌ | |
| Marketing Processes and Planning | Marketing_Processes_and_Planning | Single Line | ✓ | ❌ | |
| Materials Science | Materials_Science | Single Line | ✓ | ❌ | |
| Maths for Computing | Maths_for_Computing | Single Line | ✓ | ❌ | |
| Medical Physics Applications | Medical_Physics_Applications | Single Line | ✓ | ❌ | |
| Microbiology and Microbiological Techniques | Microbiology_and_Microbiological_Techniques | Single Line | ✓ | ❌ | |
| Modified By | Modified_By | Single Line | - | ❌ | |
| Mother Name | Mother_Name | Single Line | ✓ | ❌ | |
| National Number | National_Number | Single Line | ✓ | ❌ | |
| Nationality | Nationality | Pick List | ✓ | ❌ | |
| Networking | Networking | Single Line | ✓ | ❌ | |
| Note | Note | Multi Line (Small) | ✓ | ❌ | |
| Operating Systems | Operating_Systems | Single Line | ✓ | ❌ | |
| Operations Management | Operations_Management | Single Line | ✓ | ❌ | |
| Organisational Behaviour | Organisational_Behaviour | Single Line | ✓ | ❌ | |
| Passport Number | Passport_Number | Single Line | ✓ | ❌ | |
| Payment Method | Payment_Method | Pick List | ✓ | ❌ | |
| Payment Mode | Payment_Mode | Pick List | ✓ | ❌ | |
| Phone Number | Phone_Number | Phone | ✓ | ✅ | From Moodle phone1 |
| Pitching and Negotiation Skills | Pitching_and_Negotiation_Skills | Single Line | ✓ | ❌ | |
| Placement Test Date | Placement_Test_Date | Date | ✓ | ❌ | |
| Placement Test Result | Placement_Test_Result | Pick List | ✓ | ❌ | |
| Planning a Computing Project | Planning_a_Computing_Project | Single Line | ✓ | ❌ | |
| Pollution and Waste Management | Pollution_and_Waste_Management | Single Line | ✓ | ❌ | |
| Principles and Applications of Biology | Principles_and_Applications_of_Biology | Single Line | ✓ | ❌ | |
| Principles and Applications of Chemistry | Principles_and_Applications_of_Chemistry | Single Line | ✓ | ❌ | |
| Principles and Applications of Physics | Principles_and_Applications_of_Physics | Single Line | ✓ | ❌ | |
| Principles of Operations Management | Principles_of_Operations_Management | Single Line | ✓ | ❌ | |
| Procurement and Supply Chain Management | Procurement_and_Supply_Chain_Management | Single Line | ✓ | ❌ | |
| Professional Development for Strategic Managers | Professional_Development_for_Strategic_Managers | Single Line | ✓ | ❌ | |
| Professional Practice | Professional_Practiceddd | Single Line | ✓ | ❌ | |
| Program | Program | Lookup | ✓ | ❌ | Link to BTEC Programs |
| Programming | Programmingd | Single Line | ✓ | ❌ | |
| Project management, the strategic project | Project_management_the_strategic_project | Single Line | ✓ | ❌ | |
| Qualifi Level | Qualifi_Level | Pick List | ✓ | ❌ | |
| Qualifi Program | Qualifi_Program | Pick List | ✓ | ❌ | |
| Reason of scholarship | Reason_of_scholarship | Single Line | ✓ | ❌ | |
| Recording financial transactions | Recording_financial_transactions | Single Line | ✓ | ❌ | |
| Registered Units | Registered_Units | Lookup | ✓ | ❌ | |
| Registration Date | Registration_Date | Date | ✓ | ❌ | |
| Registration Fees | Registration_Fees | Currency | ✓ | ❌ | |
| Research Methods | Research_Methods | Single Line | ✓ | ❌ | |
| Research Methods for Strategic Managers | Research_Methods_for_Strategic_Managers | Single Line | ✓ | ❌ | |
| Research Project | Research_Project | Single Line | ✓ | ❌ | |
| Research Project (Pearson Set) | Research_Project_Pearson_Set | Single Line | ✓ | ❌ | |
| Resource and Talent Planning | Resource_and_Talent_Planning | Single Line | ✓ | ❌ | |
| Scholarship | Scholarship | Pick List | ✓ | ❌ | |
| Scholarship Percentage % | Scholarship_Percentage | Percent | ✓ | ❌ | |
| Secondary Email | Secondary_Email | Email | ✓ | ❌ | |
| Security | Security | Single Line | ✓ | ❌ | |
| Service Type | Service_Type | Pick List | ✓ | ❌ | |
| Social Media Practice | Social_Media_Practice | Single Line | ✓ | ❌ | |
| Software Development Lifecycles | Software_Development_Lifecycles | Single Line | ✓ | ❌ | |
| SRM_Created_At | SRM_Created_At | Single Line | ✓ | ❌ | |
| SRM_Educational_level | SRM_Educational_level | Single Line | ✓ | ❌ | |
| SRM_Student_Created_By | SRM_Student_Created_By | Single Line | ✓ | ❌ | |
| SRM_Updated_At | SRM_Updated_At | Single Line | ✓ | ❌ | |
| Statistics for Management | Statistics_for_Management | Single Line | ✓ | ❌ | |
| Status | Status | Pick List | ✓ | ❌ | Student status |
| Strategic Change Management | Strategic_Change_Management | Single Line | ✓ | ❌ | |
| Strategic Human Resource Management | Strategic_Human_Resource_Management | Single Line | ✓ | ❌ | |
| Strategic leadership & management | Strategic_leadership_management | Single Line | ✓ | ❌ | |
| Strategic Management of Quality and Operations | Strategic_Management_of_Quality_and_Operations | Single Line | ✓ | ❌ | |
| Strategic Marketing Management | Strategic_Marketing_Management | Single Line | ✓ | ❌ | |
| Strategic Planning | Strategic_Planning | Single Line | ✓ | ❌ | |
| Strategic Quality and Systems Management | Strategic_Quality_and_Systems_Management | Single Line | ✓ | ❌ | |
| Strategic Supply Chain Management | Strategic_Supply_Chain_Management | Single Line | ✓ | ❌ | |
| Student ID | Name | Auto Number | - | ✅ | Generated by Zoho |
| Student Image | Student_Image | Image Upload | ✓ | ❌ | |
| Student Moodle ID | Student_Moodle_ID | Single Line | ✓ | ✅ | **CRITICAL - Moodle user ID** |
| Student Note | Student_Note | Multi Line (Small) | ✓ | ❌ | |
| Student Payments | Student_Payments | Subform | ✓ | ❌ | |
| Student Units | Student_Units | Subform | ✓ | ❌ | |
| Study Language | Study_Language | Pick List | ✓ | ❌ | |
| Study Mode | Study_Mode | Pick List | ✓ | ❌ | |
| Sub Major | Sub_Major | Pick List | ✓ | ❌ | |
| Subsidiary Major | Subsidiary_Major | Pick List | ✓ | ❌ | |
| Summary | Summary | Multi Line (Small) | ✓ | ❌ | |
| Sustainable Energy | Sustainable_Energy | Single Line | ✓ | ❌ | |
| Synced to Moodle | Synced_to_Moodle | Boolean | ✓ | ✅ | **Sync flag** |
| System Analysis & Design | System_Analysis_Design | Single Line | ✓ | ❌ | |
| Tag | Tag | Single Line | - | ❌ | |
| The Digital Business Transformation and Leadership | The_Digital_Business_Transformation_and_Leadership | Single Line | ✓ | ❌ | |
| The Role of Organisational Culture and Management | The_Role_of_Organisational_Culture_and_Management | Single Line | ✓ | ❌ | |
| Top up? | Top_up | Boolean | ✓ | ❌ | |
| Total | Total | Formula | ✓ | ❌ | |
| Total Amount | Total_Amount | Formula | ✓ | ❌ | |
| Understanding and Leading Change | Understanding_and_Leading_Change | Single Line | ✓ | ❌ | |
| Units Passed Count L3 | Units_Passed_Count | Number | ✓ | ❌ | |
| Units Passed Count L5 AD | Units_Passed_Count_L5_AD | Number | ✓ | ❌ | |
| Units Passed Count L5 BUS | Units_Passed_Count_L5_BUS | Number | ✓ | ❌ | |
| Units Passed Count L5 IT | Units_Passed_Count_L5_IT | Number | ✓ | ❌ | |
| Units Passed Count L7 | Units_Passed_Count_L7 | Number | ✓ | ❌ | |
| Units Taken Count | Units_Taken_Count | Number | ✓ | ❌ | |
| Univ Payment | Univ_Payment | Currency | ✓ | ❌ | |
| Univ Payment Date | Univ_Payment_Date | Date | ✓ | ❌ | |
| University Major | University_Major | Single Line | ✓ | ❌ | |
| University Name | University_Name | Pick List | ✓ | ❌ | |
| University Pathway | University_Pathway | Single Line | ✓ | ❌ | |
| Void Update | Void_Update | Single Line | ✓ | ❌ | |
| Water Quality | Water_Quality | Single Line | ✓ | ❌ | |
| Website Design & Development | Website_Design_Development | Single Line | ✓ | ❌ | |

**Total Fields:** 150+ fields  
**Moodle Integration Fields:** 8 core fields used

---

## 2. BTEC Programs

**Module:** `Prodacts`  
**Purpose:** Academic programs/courses offered

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| Book Included | Book_Included | Boolean | ✓ | |
| Book Name | Book_Name | Pick List | ✓ | |
| Book Type | Book_Type | Pick List | ✓ | |
| BTEC Program Active | Product_Active | Boolean | - | |
| BTEC Program Category | Product_Category | Pick List | - | |
| BTEC Program Code | Product_Code | Single Line | - | |
| BTEC Program Image | Record_Image | Record Image | - | |
| BTEC Program Name | Product_Name | Single Line (Unique) | - | |
| BTEC Program Owner | Owner | Lookup | - | |
| Commission Rate | Commission_Rate | Currency | - | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Course Code | Course_Code | Pick List | ✓ | |
| Course Count | Course_Count | Pick List | ✓ | |
| Course Name | Course_Name | Pick List | ✓ | |
| Created By | Created_By | Single Line | - | |
| Description | Description | Multi Line (Large) | - | |
| Handler | Handler | Lookup | - | |
| Manufacturer | Manufacturer | Pick List | - | |
| Modified By | Modified_By | Single Line | - | |
| Moodle ID | crmnmoodle__Moodle_ID | Number | ✓ | **Link to Moodle course** |
| MoodleID | MoodleID | Single Line | ✓ | |
| Package Name | Package_Name | Pick List | ✓ | |
| Program Award | Product_Sub_Category | Pick List | ✓ | |
| Program ID | Program_ID | Auto Number | ✓ | |
| Program Major | Program_Major | Pick List | ✓ | |
| Program Price | Program_Price | Currency | ✓ | |
| Program Sub Major | Program_Sub_Major | Pick List | ✓ | |
| Program Type | Program_Type | Pick List | ✓ | |
| Qty Ordered | Qty_Ordered | Decimal | - | |
| Quantity in Demand | Qty_in_Demand | Decimal | - | |
| Quantity in Stock | Qty_in_Stock | Decimal | - | |
| Reorder Level | Reorder_Level | Decimal | - | |
| Sales End Date | Sales_End_Date | Date | - | |
| Sales Start Date | Sales_Start_Date | Date | - | |
| Status | Status | Pick List | ✓ | |
| Support End Date | Support_Expiry_Date | Date | - | |
| Support Start Date | Support_Start_Date | Date | - | |
| Tag | Tag | Single Line | - | |
| Tax | Tax | Multiselect | - | |
| Taxable | Taxable | Boolean | - | |
| Unit Price | Unit_Price | Currency | - | |
| Usage Unit | Usage_Unit | Pick List | - | |
| Vendor Name | Vendor_Name | Lookup | - | |

**Total Fields:** 40 fields

---

## 3. BTEC Units

**Module:** `BTEC`  
**Purpose:** Individual units/modules within programs

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| BTEC Grading Template P-1 | BTEC_Grading_Template_P1 | Subform | ✓ | |
| BTEC Major | Unit_Major | Pick List | ✓ | |
| BTEC Unit Image | Record_Image | BTEC Unit Image | - | |
| BTEC Unit Owner | Owner | Lookup | - | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Currency | Currency | Pick List | - | |
| D1_description | D1_description | Multi Line (Large) | ✓ | |
| D2_description | D2_description | Multi Line (Large) | ✓ | |
| D3_description | D3_description | Multi Line (Large) | ✓ | |
| D4_description | D4_description | Multi Line (Large) | ✓ | |
| D5_description | D5_description | Multi Line (Large) | ✓ | |
| D6_description | D6_description | Multi Line (Large) | ✓ | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Last Sync with Moodle | Last_Sync_with_Moodle | DateTime | ✓ | **Sync timestamp** |
| M1_description | M1_description | Multi Line (Small) | ✓ | |
| M2_description | M2_description | Multi Line (Small) | ✓ | |
| M3_description | M3_description | Multi Line (Small) | ✓ | |
| M4_description | M4_description | Multi Line (Small) | ✓ | |
| M5_description | M5_description | Multi Line (Large) | ✓ | |
| M6_description | M6_description | Multi Line (Large) | ✓ | |
| M7_description | M7_description | Multi Line (Large) | ✓ | |
| M8_description | M8_description | Multi Line (Large) | ✓ | |
| M9_description | M9_description | Multi Line (Large) | ✓ | |
| Modified By | Modified_By | Single Line | - | |
| Moodle Grading Template | Moodle_Grading_Template | Single Line | ✓ | |
| P1_description | P1_description | Multi Line (Small) | ✓ | |
| P10_description | P10_description | Multi Line (Large) | ✓ | |
| P2_description | P2_description | Multi Line (Small) | ✓ | |
| P3_description | P3_description | Multi Line (Small) | ✓ | |
| P4_description | P4_description | Multi Line (Small) | ✓ | |
| P5_description | P5_description | Multi Line (Small) | ✓ | |
| P6_description | P6_description | Multi Line (Large) | ✓ | |
| P7_description | P7_description | Multi Line (Large) | ✓ | |
| P8_description | P8_description | Multi Line (Large) | ✓ | |
| P9_description | P9_description | Multi Line (Large) | ✓ | |
| Program | Program | Lookup | ✓ | Link to BTEC Programs |
| Qualifi Major | Qualifi_Major | Pick List | ✓ | |
| Registered Students | Registered_Students | Lookup | ✓ | |
| Secondary Email | Secondary_Email | Email | - | |
| Service Type | Service_Type | Pick List | ✓ | |
| Tag | Tag | Single Line | - | |
| Unit Code | Unit_Code | Single Line | ✓ | |
| Unit Credit | Unit_Credit | Single Line | ✓ | |

**Total Fields:** 47 fields

---

## 4. BTEC Classes

**Module:** `BTEC_Classes`  
**Purpose:** Class/section instances with schedules

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| Assessor | Assessor | Single Line | ✓ | |
| BTEC Class Image | Record_Image | BTEC Class Image | - | |
| BTEC Class Owner | Owner | Lookup | - | |
| BTEC Program | BTEC_Program | Lookup | ✓ | Link to program |
| Class Name | Class_Name | Single Line | ✓ | |
| Class Short Name | Class_Short_Name | Single Line | ✓ | |
| Class Status | Class_Status | Pick List | ✓ | |
| Class Study Mode | Class_Study_Mode | Pick List | ✓ | |
| Classroom | Classroom | Single Line | ✓ | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Currency | Currency | Pick List | - | |
| Day (1) | Start_Hour | Single Line | ✓ | |
| Day (2) | End_Hour | Single Line | ✓ | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| End Date | End_Date | Date | ✓ | |
| English Teaching | English_Teaching | Boolean | ✓ | |
| Enrolled Students | Enrolled_Students | Multi-Select Lookup | ✓ | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Final Evaluation Form | Final_Evaluation_Form | URL | ✓ | |
| Hours (1) | Hours_1 | Single Line | ✓ | |
| Hours (2) | Hours_2 | Single Line | ✓ | |
| Initial Evaluation Form | Teacher_Evaluation_Form | URL | ✓ | |
| Intake | Intake | Lookup | ✓ | |
| Modified By | Modified_By | Single Line | - | |
| Moodle Class ID | Moodle_Class_ID | Single Line | ✓ | **Link to Moodle course instance** |
| MS Teams ID | MS_Teams_ID | Single Line | ✓ | |
| Resubmission Assessment | Resubmission_Grade | Pick List | ✓ | |
| Secondary Email | Secondary_Email | Email | - | |
| Start Date | Start_Date | Date | ✓ | |
| Submission Assessment | First_Submission_Grade | Pick List | ✓ | |
| Tag | Tag | Single Line | - | |
| Teacher | Teacher | Lookup | ✓ | |
| Term | Term | Pick List | ✓ | |
| Unit | Unit | Lookup | ✓ | Link to BTEC Units |
| Year | Year | Single Line | ✓ | |
| Zoho ID | Name | Auto Number | - | |

**Total Fields:** 39 fields

---

## 5. BTEC Enrollments

**Module:** `BTEC_Enrollments`  
**Purpose:** Student enrollments in classes

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| BTEC Enrollments Owner | Owner | Lookup | - | |
| Class | Classes | Lookup | - | Link to BTEC Classes |
| Class Name | Class_Name | Single Line | ✓ | |
| Class Teacher | Class_Teacher | Single Line | ✓ | |
| Created By | Created_By | Single Line | - | |
| Created Time | Created_Time | DateTime | - | |
| Currency | Currency | Pick List | - | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| End Date | End_Date | Date | ✓ | |
| Enrolled Program | Enrolled_Program | Single Line | ✓ | |
| Enrollment ID | Name | Auto Number | - | |
| Enrollment Type | Enrollment_Type | Pick List | ✓ | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Last Activity Time | Last_Activity_Time | DateTime | - | |
| Last Sync Date (to moodle) | Last_Sync_Date | DateTime | ✓ | **Sync timestamp** |
| Modified By | Modified_By | Single Line | - | |
| Modified Time | Modified_Time | DateTime | - | |
| Moodle Course ID | Moodle_Course_ID | Single Line | ✓ | **Link to Moodle course** |
| Recording | Recording | Boolean | ✓ | |
| Recording Attending Term | Recording_Attending_Term | Single Line | ✓ | |
| Secondary Email | Secondary_Email | Email | - | |
| Start Date | Start_Date | Date | ✓ | |
| Student | Enrolled_Students | Lookup | - | Link to BTEC Students |
| Student Name | Student_Name | Single Line | ✓ | |
| Synced to Moodle | Synced_to_Moodle | Boolean | ✓ | **Sync flag** |

**Total Fields:** 26 fields

---

## 6. BTEC Grades

**Module:** `BTEC_Grades`  
**Purpose:** Student grades for units/assignments

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| Attempt Date | Attempt_Date | Date | ✓ | |
| Attempt Number | Attempt_Number | Number | ✓ | |
| BTEC Grade Image | Record_Image | BTEC Grade Image | - | |
| BTEC Grade Name | BTEC_Grade_Name | Single Line | ✓ | |
| BTEC Grade Owner | Owner | Lookup | - | |
| BTEC Unit | BTEC_Unit | Single Line | ✓ | |
| Class ID | Class | Lookup | ✓ | Link to BTEC Classes |
| Class Name | Class_Name | Single Line | ✓ | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Currency | Currency | Pick List | - | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Feedback | Feedback | Multi Line (Small) | ✓ | |
| Grade | Grade | Pick List | ✓ | BTEC letter grade |
| Grade Record ID | Name | Auto Number | - | |
| Grade Status | Grade_Status | Pick List | ✓ | |
| Grader Name | Grader_Name | Single Line | ✓ | |
| IV Name | IV_Name | Single Line | ✓ | |
| Last Sync Date (To Moodle) | Last_Sync_Date | DateTime | ✓ | **Sync timestamp** |
| Learning Outcomes Assessm | Learning_Outcomes_Assessm | Subform | ✓ | |
| Modified By | Modified_By | Single Line | - | |
| Moodle Grade Composite_Key | Moodle_Grade_Composite_Key | Single Line (Unique) | ✓ | **Unique identifier** |
| Moodle Grade ID | Moodle_Grade_ID | Single Line | ✓ | **Link to Moodle grade** |
| Secondary Email | Secondary_Email | Email | - | |
| Student | Student | Lookup | ✓ | Link to BTEC Students |
| Student Name | Student_Name | Single Line | ✓ | |
| Synced to Moodle | Synced_to_Moodle | Boolean | ✓ | **Sync flag** |
| Tag | Tag | Single Line | - | |

**Total Fields:** 30 fields

---

## 7. BTEC Teachers

**Module:** `BTEC_Teachers`  
**Purpose:** Teacher/instructor records

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| Academic Email | Academic_Email | Email (Unique) | ✓ | |
| BTEC Teacher Image | Record_Image | BTEC Teacher Image | - | |
| BTEC Teacher Name | Name | Single Line | - | |
| BTEC Teacher Owner | Owner | Lookup | - | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Currency | Currency | Pick List | - | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Modified By | Modified_By | Single Line | - | |
| Phone Number | Phone_Number | Phone | ✓ | |
| Secondary Email | Secondary_Email | Email | - | |
| Tag | Tag | Single Line | - | |
| Teacher Moodle ID | Teacher_Moodle_ID | Single Line | ✓ | **Link to Moodle user** |

**Total Fields:** 15 fields

---

## 8. BTEC Registrations

**Module:** `BTEC_Registrations`  
**Purpose:** Program registration records

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| BTEC Reg No | BTEC_Reg_No | Single Line | ✓ | |
| BTEC Registration Image | Record_Image | BTEC Registration Image | - | |
| BTEC Registration Owner | Owner | Lookup | - | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Created Date | SRM_Created_At | Date | ✓ | |
| Currency | Currency | Pick List | - | |
| Discount | Discount | Boolean | ✓ | |
| Discount Percentage | Discount_Percentage | Single Line | ✓ | |
| Discount Reason | Discount_Reason | Single Line | ✓ | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| Employee | Registration_Owner | Single Line | ✓ | |
| EOL Number | EOL_Number | Single Line | ✓ | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| First Name | First_Name | Single Line | ✓ | |
| Intake | Intake | Single Line | ✓ | |
| Last Name | Last_Name | Single Line | ✓ | |
| Last Sync Date (to Moodle) | Last_Sync_Date | DateTime | ✓ | **Sync timestamp** |
| Major | Major | Single Line | ✓ | |
| Modified By | Modified_By | Single Line | - | |
| National ID_Passport | National_ID_Passport | Single Line | ✓ | |
| Note | Note | Multi Line (Small) | ✓ | |
| Passport Number | Passport_Number | Single Line | ✓ | |
| Payment Schedule | Payment_Schedule | Subform | ✓ | |
| Program | Program | Lookup | ✓ | Link to BTEC Programs |
| Program Price | Program_Price | Single Line | ✓ | |
| Registration Date | Registration_Date | Date | ✓ | |
| Registration ID | Name | Auto Number | - | |
| Registration Note | Registration_Note | Multi Line (Small) | ✓ | |
| Registration Status | Registration_Status | Pick List | ✓ | |
| Remaining Amount | Remaining_Amount | Single Line | ✓ | |
| Secondary Email | Secondary_Email | Email | - | |
| SRM Note | SRM_Note | Single Line | ✓ | |
| SRM Owner | SRM_Owner | Date | ✓ | |
| Student ID | Student_ID | Lookup | ✓ | Link to BTEC Students |
| Student Status | Student_Status | Single Line | ✓ | |
| Study Language | Study_Language | Pick List | ✓ | |
| Study Mode | Study_Mode | Pick List | ✓ | |
| Sub Major | Sub_Major | Single Line | ✓ | |
| Synced to Moodle | Synced_to_Moodle | Boolean | ✓ | **Sync flag** |
| Tag | Tag | Single Line | - | |
| Updated Date | SRM_Updated_At | Date | ✓ | |
| Void | Void | Single Line | ✓ | |

**Total Fields:** 44 fields

---

## 9. BTEC Payments

**Module:** `BTEC_Payments`  
**Purpose:** Payment transaction records

| Field Label | API Name | Data Type | Custom | Notes |
|------------|----------|-----------|--------|-------|
| Accepted | SRM_Active | Single Line | ✓ | |
| BTEC Payment Image | Record_Image | BTEC Payment Image | - | |
| BTEC Payment Owner | Owner | Lookup | - | |
| Connected To | Connected_To_s | MultiModuleLookup | - | |
| Created By | Created_By | Single Line | - | |
| Created Date | Created_Date | DateTime | ✓ | |
| Currency | Currency | Pick List | - | |
| Decline Reason | SRM_Decline_Reason | Single Line | ✓ | |
| Email | Email | Email | - | |
| Email Opt Out | Email_Opt_Out | Boolean | - | |
| Employee | SRM_Payment_Created_By | Single Line | ✓ | |
| Exchange Rate | Exchange_Rate | Decimal | - | |
| Installment No | Installment_No | Number | ✓ | |
| Last Sync Date (To Moodle) | Last_Sync_Date | DateTime | ✓ | **Sync timestamp** |
| Modified By | Modified_By | Single Line | - | |
| Note | Note | Multi Line (Small) | ✓ | |
| Original Amount | SRM_Original_Amount | Currency | ✓ | |
| Original Currency | SRM_Original_Currency | Single Line | ✓ | |
| Payment Amount | Payment_Amount | Currency | ✓ | |
| Payment Date | Payment_Date | Date | ✓ | |
| Payment ID | Name | Auto Number | - | |
| Payment Method | Payment_Method | Pick List | ✓ | |
| Payment Type | Payment_Type | Pick List | ✓ | |
| Registration ID | Registration_ID | Lookup | ✓ | Link to Registrations |
| Secondary Email | Secondary_Email | Email | - | |
| Student ID | Student_ID | Lookup | ✓ | Link to Students |
| Synced to Moodle | Synced_to_Moodle | Boolean | ✓ | **Sync flag** |
| Tag | Tag | Single Line | - | |
| Updated Date | Updated_Date | DateTime | ✓ | |
| Voucher Number | SRM_Voucher_Number | Single Line | ✓ | |

**Total Fields:** 30 fields

---

## Moodle Integration Fields

### Critical Fields for Moodle ↔ Zoho Sync

#### Students Table
| Zoho Field | API Name | Usage | Direction |
|-----------|----------|-------|-----------|
| Student Moodle ID | Student_Moodle_ID | **Primary Link** | Zoho → Moodle |
| Academic Email | Academic_Email | User identifier | Bidirectional |
| First Name | First_Name | User info | Bidirectional |
| Last Name | Last_Name | User info | Bidirectional |
| Phone Number | Phone_Number | Contact | Bidirectional |
| City | City | Location | Bidirectional |
| Last Sync Date | Last_Sync_Date | Sync tracking | Zoho → Moodle |
| Synced to Moodle | Synced_to_Moodle | Sync flag | Zoho → Moodle |

#### Programs/Classes Table
| Zoho Field | API Name | Usage | Direction |
|-----------|----------|-------|-----------|
| Moodle ID | crmnmoodle__Moodle_ID | Course link | Zoho → Moodle |
| Moodle Class ID | Moodle_Class_ID | Class instance | Zoho → Moodle |

#### Enrollments Table
| Zoho Field | API Name | Usage | Direction |
|-----------|----------|-------|-----------|
| Moodle Course ID | Moodle_Course_ID | Course link | Zoho → Moodle |
| Last Sync Date | Last_Sync_Date | Sync tracking | Zoho → Moodle |
| Synced to Moodle | Synced_to_Moodle | Sync flag | Zoho → Moodle |

#### Grades Table
| Zoho Field | API Name | Usage | Direction |
|-----------|----------|-------|-----------|
| Moodle Grade ID | Moodle_Grade_ID | Grade link | Moodle → Zoho |
| Moodle Grade Composite_Key | Moodle_Grade_Composite_Key | Unique ID | Moodle → Zoho |
| Last Sync Date | Last_Sync_Date | Sync tracking | Moodle → Zoho |
| Synced to Moodle | Synced_to_Moodle | Sync flag | Moodle → Zoho |
| Grade | Grade | BTEC grade | Moodle → Zoho |
| Feedback | Feedback | Comments | Moodle → Zoho |

#### Units Table
| Zoho Field | API Name | Usage | Direction |
|-----------|----------|-------|-----------|
| Last Sync with Moodle | Last_Sync_with_Moodle | Sync tracking | Bidirectional |

---

## Field Data Types Reference

| Zoho Type | Description | Example |
|-----------|-------------|---------|
| Single Line | Short text (255 chars) | "John Doe" |
| Multi Line (Small) | Medium text (2000 chars) | Feedback |
| Multi Line (Large) | Long text (32000 chars) | Descriptions |
| Email | Email address | "john@example.com" |
| Email (Unique) | Unique email | Primary emails |
| Phone | Phone number | "+962791234567" |
| Pick List | Dropdown selection | Status, Grade |
| Multiselect | Multiple selections | Tags |
| Date | Date only | "2026-01-26" |
| DateTime | Date + Time | "2026-01-26T10:30:00" |
| Number | Integer | 123 |
| Decimal | Floating point | 85.5 |
| Currency | Money value | 1500.00 |
| Percent | Percentage | 15% |
| Boolean | True/False | Synced flag |
| Auto Number | Auto-generated ID | "STU-001" |
| Lookup | Link to another module | Student → Program |
| Multi-Select Lookup | Multiple links | Class → Students |
| MultiModuleLookup | Links to multiple modules | - |
| Subform | Embedded table | Payment Schedule |
| Formula | Calculated field | Total Amount |
| Image Upload | Image file | Student photo |
| Record Image | Module image | Program image |
| URL | Web link | Forms, Links |

---

## Usage Notes

### 🎯 For Moodle Plugin Development
- Focus on fields marked with "Moodle Used" = ✅
- Use `Student_Moodle_ID` as primary identifier
- Always update `Last_Sync_Date` after sync
- Set `Synced_to_Moodle` = true after successful sync

### 📝 For Backend API
- Map Moodle user.id to `Student_Moodle_ID`
- Map Moodle grade to BTEC `Grade` (Distinction/Merit/Pass/Refer)
- Store `Moodle_Grade_Composite_Key` for idempotency
- Track all sync operations with timestamps

### 🔄 Sync Direction
- **Zoho → Moodle:** Students, Enrollments (initial setup)
- **Moodle → Zoho:** Grades, Attendance, Updates
- **Bidirectional:** Profile updates, Status changes

---

## Field Naming Conventions

### Zoho API Names
- **CamelCase:** `First_Name`, `Student_Moodle_ID`
- **Underscores:** Words separated by `_`
- **Prefixes:**
  - `SRM_` = From SRM system
  - `crmnmoodle__` = Moodle integration fields
  - No prefix = Standard Zoho fields

### Custom Fields
- Most fields are custom (✓ in Custom column)
- System fields: Owner, Created_By, Modified_By, Name (ID)
- Integration fields: Moodle_*, Synced_to_*, Last_Sync_*

---

## Summary Statistics

| Module | Total Fields | Custom Fields | Moodle Integration Fields |
|--------|--------------|---------------|---------------------------|
| BTEC Students | 150+ | 140+ | 8 |
| BTEC Programs | 40 | 15 | 2 |
| BTEC Units | 47 | 40 | 1 |
| BTEC Classes | 39 | 30 | 1 |
| BTEC Enrollments | 26 | 15 | 3 |
| BTEC Grades | 30 | 20 | 5 |
| BTEC Teachers | 15 | 5 | 1 |
| BTEC Registrations | 44 | 35 | 1 |
| BTEC Payments | 30 | 20 | 1 |
| **TOTAL** | **420+** | **320+** | **23** |

---

## Quick Reference - Most Used Fields

### User Management
- `Student_Moodle_ID` - Link to Moodle user
- `Academic_Email` - Primary email
- `First_Name`, `Last_Name` - Name fields
- `Synced_to_Moodle` - Sync status

### Course/Class Management
- `Moodle_Class_ID` - Link to Moodle course
- `crmnmoodle__Moodle_ID` - Program course ID
- `Enrolled_Students` - Students in class

### Grade Management
- `Moodle_Grade_ID` - Link to Moodle grade
- `Moodle_Grade_Composite_Key` - Unique identifier
- `Grade` - BTEC letter grade
- `Feedback` - Grading comments

### Sync Tracking
- `Last_Sync_Date` - Last sync timestamp
- `Synced_to_Moodle` - Sync completion flag
- `Last_Sync_with_Moodle` - Alternative timestamp field

---

---

## 🔑 Critical Field Mapping: Moodle ↔ Backend ↔ Zoho

### Overview

```
Moodle Database → Moodle Plugin → Backend API → PostgreSQL → Zoho CRM API
```

---

### 1. User/Student Mapping

#### Primary Keys & Identifiers

| Entity | Moodle Field | Backend DB Field | Zoho API Field | Data Type | Notes |
|--------|-------------|------------------|----------------|-----------|-------|
| **Primary Key** | `mdl_user.id` | `students.moodle_user_id` | `Student_Moodle_ID` | Integer/String | **PRIMARY LINK** |
| Zoho ID | - | `students.zoho_id` | `Name` (Student ID) | String | Auto Number in Zoho |
| Backend ID | - | `students.id` | - | UUID | Internal PK |
| Username | `mdl_user.username` | `students.username` | - | String | Unique |
| Email | `mdl_user.email` | `students.academic_email` | `Academic_Email` | Email (Unique) | Required |

#### User Data Fields

| Moodle DB Field | Moodle Table | Backend Model | Backend Field | Zoho Field | Transform |
|----------------|--------------|---------------|---------------|------------|-----------|
| `id` | mdl_user | Student | moodle_user_id | Student_Moodle_ID | string(int) |
| `username` | mdl_user | Student | username | - | direct |
| `firstname` | mdl_user | Student | display_name* | First_Name | firstname + lastname |
| `lastname` | mdl_user | Student | display_name* | Last_Name | firstname + lastname |
| `email` | mdl_user | Student | academic_email | Academic_Email | direct |
| `idnumber` | mdl_user | Student | userid | - | nullable |
| `phone1` | mdl_user | Student | phone | Phone_Number | nullable |
| `city` | mdl_user | Student | city | City | nullable |
| `country` | mdl_user | Student | country | - | 2-letter code |
| `suspended` | mdl_user | - | - | - | skip if true |
| `deleted` | mdl_user | - | - | - | skip if true |
| `timecreated` | mdl_user | Student | created_at | - | timestamp → datetime |
| `timemodified` | mdl_user | Student | updated_at | Last_Sync_Date | timestamp → datetime |

**Moodle PHP Extraction:**
```php
$user = $DB->get_record('user', ['id' => $event->relateduserid]);

$data = [
    'userid' => (int)$user->id,               // → moodle_user_id
    'username' => $user->username,            // → username
    'firstname' => $user->firstname,          // → used in display_name
    'lastname' => $user->lastname,            // → used in display_name
    'email' => $user->email,                  // → academic_email (REQUIRED)
    'idnumber' => $user->idnumber ?: '',      // → userid (nullable)
    'phone1' => $user->phone1 ?: '',          // → phone (nullable)
    'city' => $user->city ?: '',              // → city (nullable)
    'country' => $user->country ?: '',        // → country (nullable)
    'suspended' => (bool)$user->suspended,    // → Skip if true
    'deleted' => (bool)$user->deleted,        // → Skip if true
    'timecreated' => (int)$user->timecreated,
    'timemodified' => (int)$user->timemodified,
];
```

---

### 2. Enrollment Mapping

#### Primary Keys & Identifiers

| Entity | Moodle Field | Backend DB Field | Zoho API Field | Notes |
|--------|-------------|------------------|----------------|-------|
| **Enrollment ID** | `mdl_user_enrolments.id` | `enrollments.moodle_enrollment_id` | - | Moodle enrolment ID |
| **User ID** | `mdl_user_enrolments.userid` | `enrollments.moodle_user_id` | - | Link to student |
| **Course ID** | `mdl_enrol.courseid` | `enrollments.moodle_course_id` | `Moodle_Course_ID` | Link to course |
| Zoho ID | - | `enrollments.zoho_id` | `Name` (Enrollment ID) | Auto Number |
| Backend ID | - | `enrollments.id` | - | UUID (PK) |

#### Enrollment Data Fields

| Moodle DB Field | Moodle Table | Backend Field | Zoho Field | Notes |
|----------------|--------------|---------------|------------|-------|
| `id` | mdl_user_enrolments | moodle_enrollment_id | - | Moodle enrolment PK |
| `userid` | mdl_user_enrolments | moodle_user_id | - | User reference |
| `enrolid` → `courseid` | mdl_enrol | moodle_course_id | Moodle_Course_ID | Course reference |
| `status` | mdl_user_enrolments | status | - | 0=active, 1=suspended |
| `timestart` | mdl_user_enrolments | start_date | Start_Date | timestamp → date |
| `timeend` | mdl_user_enrolments | - | End_Date | timestamp → date |
| `timecreated` | mdl_user_enrolments | created_at | Created_Time | timestamp → datetime |

**Moodle PHP Extraction:**
```php
$enrolment = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
$enrol = $DB->get_record('enrol', ['id' => $enrolment->enrolid]);

$data = [
    'enrollmentid' => (int)$enrolment->id,      // → moodle_enrollment_id
    'userid' => (int)$enrolment->userid,        // → moodle_user_id
    'courseid' => (int)$enrol->courseid,        // → moodle_course_id
    'roleid' => 5,                              // Student role (hardcoded)
    'status' => (int)$enrolment->status,        // 0=active, 1=suspended
    'timestart' => (int)$enrolment->timestart,
    'timeend' => (int)$enrolment->timeend,
    'timecreated' => (int)$enrolment->timecreated,
];
```

---

### 3. Grade Mapping

#### Primary Keys & Identifiers

| Entity | Moodle Field | Backend DB Field | Zoho API Field | Notes |
|--------|-------------|------------------|----------------|-------|
| **Grade ID** | `mdl_grade_grades.id` | - | `Moodle_Grade_ID` | Moodle grade PK |
| **User ID** | `mdl_grade_grades.userid` | - | - | Student reference |
| **Item ID** | `mdl_grade_grades.itemid` | - | - | Assignment/quiz ID |
| **Composite Key** | - | - | `Moodle_Grade_Composite_Key` | user_item_unique |
| Zoho ID | - | `grades.zoho_id` | `Name` (Grade Record ID) | Auto Number |
| Backend ID | - | `grades.id` | - | UUID (PK) |

#### Grade Data Fields

| Moodle DB Field | Moodle Table | Backend Field | Zoho Field | Transform |
|----------------|--------------|---------------|------------|-----------|
| `id` | mdl_grade_grades | - | Moodle_Grade_ID | string |
| `userid` | mdl_grade_grades | - | - | Link to student |
| `itemid` | mdl_grade_grades | - | - | Grade item ID |
| `itemname` | mdl_grade_items | - | BTEC_Unit | Item/Unit name |
| `finalgrade` | mdl_grade_grades | score | - | 0-100 numeric |
| **BTEC Grade** | - | grade_value | Grade | **CONVERSION** ⬇️ |
| `feedback` | mdl_grade_grades | comments | Feedback | Text feedback |
| `usermodified` | mdl_grade_grades | - | Grader_Name | Grader ID |
| `timecreated` | mdl_grade_grades | created_at | - | timestamp → datetime |
| `timemodified` | mdl_grade_grades | updated_at | Last_Sync_Date | timestamp → datetime |

#### 🎯 BTEC Grade Conversion Logic

**Backend Python (in moodle_events.py):**
```python
def convert_moodle_grade(finalgrade: Optional[float]) -> str:
    """Convert Moodle numeric grade (0-100) to BTEC letter grade"""
    if finalgrade is None:
        return "Not Graded"
    if finalgrade >= 70:
        return "Distinction"    # D
    elif finalgrade >= 60:
        return "Merit"          # M
    elif finalgrade >= 40:
        return "Pass"           # P
    else:
        return "Refer"          # R (Fail)
```

**Moodle PHP Extraction:**
```php
$grade = $DB->get_record('grade_grades', ['id' => $event->objectid]);
$grade_item = $DB->get_record('grade_items', ['id' => $grade->itemid]);

$data = [
    'gradeid' => (int)$grade->id,                   // → Moodle_Grade_ID
    'userid' => (int)$grade->userid,                // → Link to student
    'itemid' => (int)$grade->itemid,                // → Grade item
    'itemname' => $grade_item->itemname,            // → Unit name
    'finalgrade' => (float)$grade->finalgrade,      // → Converted to BTEC
    'feedback' => $grade->feedback ?: '',           // → Feedback
    'grader' => (int)$grade->usermodified,          // → Grader
    'timecreated' => (int)$grade->timecreated,
    'timemodified' => (int)$grade->timemodified,
];
```

---

## 🔄 Data Flow Diagrams

### User Creation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ MOODLE                                                          │
├─────────────────────────────────────────────────────────────────┤
│ 1. Admin creates user in Moodle UI                             │
│    • mdl_user.id = 123                                         │
│    • mdl_user.firstname = "John"                               │
│    • mdl_user.lastname = "Doe"                                 │
│    • mdl_user.email = "john@example.com"                       │
│                                                                 │
│ 2. \core\event\user_created triggered                          │
│    ↓                                                           │
│ 3. local_backend_sync_observer::user_created()                 │
│    • Extracts user data from mdl_user table                    │
│    • Builds JSON payload                                       │
│    ↓                                                           │
│ 4. webhook_sender::send()                                      │
│    • POST /api/v1/events/moodle/user_created                  │
│    • Headers: X-Moodle-Token, X-Tenant-ID                     │
│    • Body: {userid: 123, firstname: "John", ...}              │
└─────────────────────────────────────────────────────────────────┘
                            │
                            │ HTTP POST
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│ BACKEND API                                                     │
├─────────────────────────────────────────────────────────────────┤
│ 5. /api/v1/events/moodle/user_created endpoint                 │
│    • Validates request (Pydantic MoodleUserEvent)              │
│    • Checks if user exists (by moodle_user_id)                 │
│    ↓                                                           │
│ 6. Database operation                                          │
│    INSERT INTO students (                                      │
│      id = UUID(),                                              │
│      tenant_id = "default",                                    │
│      source = "moodle",                                        │
│      moodle_user_id = "123",          ← CRITICAL FIELD        │
│      username = "john@example.com",                            │
│      display_name = "John Doe",                                │
│      academic_email = "john@example.com",                      │
│      status = "active"                                         │
│    )                                                           │
│    ↓                                                           │
│ 7. Return 200 OK                                              │
│    {success: true, message: "User created", event_id: "..."}  │
└─────────────────────────────────────────────────────────────────┘
```

---

## ⚠️ Critical Implementation Notes

### 1. Moodle Plugin Must Send

✅ **REQUIRED Fields:**
- `userid` (Moodle user.id) - **PRIMARY IDENTIFIER**
- `email` - Required for all operations
- `firstname` + `lastname` - Required for display

✅ **RECOMMENDED Fields:**
- `phone1` - Contact information
- `city` - Location data
- `idnumber` - Student ID (if available)

❌ **DO NOT Send:**
- `suspended=true` or `deleted=true` users (skip these)

### 2. Backend Must Store

✅ **PRIMARY KEY:**
- `moodle_user_id` - **CRITICAL** for lookups

✅ **REQUIRED:**
- `academic_email` - Cannot be NULL
- `username` - Unique identifier

✅ **NULLABLE (Optional):**
- `zoho_id` - Populated after Zoho sync
- `phone`, `city`, `country` - Optional data

---

## 🔑 Understanding Moodle-Prefixed Fields in Zoho

### Question: هل الحقول يلي فيها كلمة Moodle في Zoho هي حقول مفتاحية؟

**الجواب: نعم! لكن ليس كـ Primary Keys، بل كـ Foreign Keys / Link Fields**

---

### 1. الحقول المفتاحية في Zoho (Moodle-Prefixed)

#### ✅ الحقول الأساسية للربط

| Zoho Module | Zoho Field | Purpose | Moodle Source | Type |
|-------------|-----------|---------|---------------|------|
| BTEC_Students | **Student_Moodle_ID** | 🔗 **Link to Moodle User** | `mdl_user.id` | **FOREIGN KEY** |
| BTEC_Enrollments | **Moodle_Course_ID** | 🔗 Link to Moodle Course | `mdl_course.id` | **FOREIGN KEY** |
| BTEC_Grades | **Moodle_Grade_ID** | 🔗 Link to Moodle Grade | `mdl_grade_grades.id` | **FOREIGN KEY** |
| BTEC_Grades | **Moodle_Grade_Composite_Key** | 🆔 Unique Identifier | `userid_itemid` | **UNIQUE INDEX** |
| BTEC_Classes | **Moodle_Class_ID** | 🔗 Link to Moodle Course | `mdl_course.id` | **FOREIGN KEY** |
| BTEC_Programs | **crmnmoodle__Moodle_ID** | 🔗 Link to Moodle Course | `mdl_course.id` | **FOREIGN KEY** |
| BTEC_Teachers | **Teacher_Moodle_ID** | 🔗 Link to Moodle User | `mdl_user.id` | **FOREIGN KEY** |
| BTEC_Units | **Last_Sync_with_Moodle** | 🕒 Sync Timestamp | - | **METADATA** |

---

### 2. الفرق بين Primary Key و Foreign Key

#### Primary Keys (المفاتيح الأساسية)

| System | Table/Module | Primary Key | Type | Generated By |
|--------|-------------|-------------|------|--------------|
| **Moodle** | mdl_user | `id` | Integer (Auto) | Moodle |
| **Backend** | students | `id` | UUID | Backend |
| **Zoho** | BTEC_Students | `Name` (Student ID) | Auto Number | Zoho |

**خصائص Primary Keys:**
- ✅ فريدة (Unique) ومُفهرسة (Indexed)
- ✅ لا يمكن أن تكون NULL
- ✅ تُستخدم لتحديد السجل بشكل فريد داخل نفس النظام
- ✅ يتم توليدها تلقائياً

#### Foreign Keys (مفاتيح الربط)

| System | Field | Links To | Purpose |
|--------|-------|----------|---------|
| **Backend** | `students.moodle_user_id` | Moodle `mdl_user.id` | ربط الطالب بسجله في Moodle |
| **Backend** | `students.zoho_id` | Zoho `Name` (Student ID) | ربط الطالب بسجله في Zoho |
| **Zoho** | `Student_Moodle_ID` | Moodle `mdl_user.id` | ربط عكسي من Zoho إلى Moodle |

**خصائص Foreign Keys:**
- ✅ تربط بين جدولين/نظامين مختلفين
- ✅ يمكن أن تكون NULL (قبل المزامنة)
- ✅ تُستخدم للـ Lookups والـ Joins
- ✅ **ليست** فريدة بالضرورة

---

### 3. استخدام Student_Moodle_ID كمثال

#### 🔄 Data Flow & Usage

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   MOODLE    │         │   BACKEND    │         │   ZOHO CRM  │
├─────────────┤         ├──────────────┤         ├─────────────┤
│ mdl_user    │         │ students     │         │ BTEC_Stud.. │
│             │         │              │         │             │
│ id = 123 ◄──┼────────►│ moodle_...=  │         │             │
│   (PK)      │  Link   │   "123"   ◄──┼────────►│ Student_... │
│             │         │   (FK)       │  Sync   │   = "123"   │
│             │         │              │         │   (FK)      │
│             │         │ zoho_id = ───┼────────►│ Name =      │
│             │         │   "539..." ◄─┼─────────│   "STU-001" │
│             │         │   (FK)       │  Link   │   (PK)      │
└─────────────┘         └──────────────┘         └─────────────┘

      PK                FK       FK               FK      PK
   (Internal)        (Link)  (Link)            (Link) (Internal)
```

#### ✅ الاستخدام الصحيح

**1. Moodle → Backend (Initial Sync)**
```python
# Webhook من Moodle
webhook_data = {
    "userid": 123,  # Moodle PK
    "email": "john@example.com",
    # ...
}

# Backend يخزن
student = Student(
    id=uuid4(),                    # Backend PK (UUID)
    moodle_user_id="123",          # ← FOREIGN KEY to Moodle
    zoho_id=None,                  # ← Will be filled after Zoho sync
    academic_email="john@example.com"
)
```

**2. Backend → Zoho (Sync)**
```python
# Query: Find students to sync
students = db.query(Student).filter(
    Student.source == "moodle",
    Student.sync_status == "pending",
    Student.zoho_id.is_(None)      # Not yet synced to Zoho
).all()

# Sync to Zoho
for student in students:
    zoho_data = {
        "First_Name": "John",
        "Last_Name": "Doe",
        "Academic_Email": student.academic_email,
        "Student_Moodle_ID": student.moodle_user_id,  # ← FOREIGN KEY to Moodle
        # ...
    }
    
    response = zoho_api.create_record("BTEC_Students", zoho_data)
    
    # Update backend with Zoho ID
    student.zoho_id = response['id']  # ← FOREIGN KEY to Zoho
    student.sync_status = "synced"
    db.commit()
```

**3. Zoho → Backend (Lookup/Query)**
```python
# Search in Zoho by Moodle ID
search_criteria = f"(Student_Moodle_ID:equals:123)"
zoho_records = zoho_api.search_records("BTEC_Students", search_criteria)

# Now we have the Zoho record linked to Moodle user 123
```

**4. Backend ← Zoho (Webhook Update)**
```python
# Zoho webhook: Student updated
zoho_webhook = {
    "record_id": "5398830000123456",  # Zoho PK
    "data": {
        "Student_Moodle_ID": "123",   # ← FOREIGN KEY
        "Phone_Number": "+962791234567"
    }
}

# Find in backend by zoho_id
student = db.query(Student).filter(
    Student.zoho_id == zoho_webhook['record_id']
).first()

# Or find by moodle_user_id
student = db.query(Student).filter(
    Student.moodle_user_id == zoho_webhook['data']['Student_Moodle_ID']
).first()

# Update student
student.phone = zoho_webhook['data']['Phone_Number']
db.commit()
```

---

### 4. سيناريوهات الاستخدام

#### ✅ سيناريو 1: البحث عن طالب من خلال Moodle ID

**في Backend:**
```python
# Find student by Moodle user ID
student = db.query(Student).filter(
    Student.moodle_user_id == "123"
).first()

print(f"Found: {student.display_name}")
print(f"Zoho ID: {student.zoho_id}")
```

**في Zoho (via API):**
```python
# Search for student in Zoho by Moodle ID
criteria = "(Student_Moodle_ID:equals:123)"
results = zoho_api.search_records("BTEC_Students", criteria)

if results:
    zoho_student = results[0]
    print(f"Zoho Record ID: {zoho_student['id']}")
    print(f"Name: {zoho_student['First_Name']} {zoho_student['Last_Name']}")
```

#### ✅ سيناريو 2: منع التكرار (Idempotency)

**Problem:** نفس الـ webhook يُرسل مرتين من Moodle

**Solution:** استخدام `Student_Moodle_ID` للتحقق

```python
# Check if student exists before creating
existing = db.query(Student).filter(
    Student.moodle_user_id == webhook_data['userid']
).first()

if existing:
    # Update existing record
    existing.academic_email = webhook_data['email']
    existing.updated_at = datetime.now()
    print("✅ Updated existing student")
else:
    # Create new record
    new_student = Student(
        moodle_user_id=str(webhook_data['userid']),
        academic_email=webhook_data['email']
    )
    db.add(new_student)
    print("✅ Created new student")

db.commit()
```

#### ✅ سيناريو 3: Bidirectional Sync (مزامنة ثنائية الاتجاه)

**Moodle → Zoho:**
```python
# 1. Moodle webhook → Backend
# 2. Backend stores with moodle_user_id
# 3. Backend syncs to Zoho with Student_Moodle_ID
# 4. Zoho stores Student_Moodle_ID as reference
```

**Zoho → Moodle:**
```python
# 1. Zoho webhook → Backend
# 2. Backend finds student by zoho_id or Student_Moodle_ID
# 3. Backend gets moodle_user_id
# 4. Backend calls Moodle API to update user
```

---

### 5. حقول أخرى للربط (Other Link Fields)

#### Enrollment Links

| Field | Purpose | Links To |
|-------|---------|----------|
| `Moodle_Course_ID` | ربط التسجيل بالكورس | `mdl_course.id` |
| `Student_Moodle_ID` | (via Student lookup) | `mdl_user.id` |
| `Classes` (Lookup) | ربط بالصف في Zoho | BTEC_Classes |

#### Grade Links

| Field | Purpose | Links To |
|-------|---------|----------|
| `Moodle_Grade_ID` | رقم الدرجة الفريد | `mdl_grade_grades.id` |
| `Moodle_Grade_Composite_Key` | مفتاح مركب فريد | `{userid}_{itemid}` |
| `Student` (Lookup) | ربط بالطالب | BTEC_Students |
| `Class` (Lookup) | ربط بالصف | BTEC_Classes |

#### Class/Course Links

| Field | Purpose | Links To |
|-------|---------|----------|
| `Moodle_Class_ID` | رقم الكورس في Moodle | `mdl_course.id` |
| `BTEC_Program` (Lookup) | ربط بالبرنامج | BTEC_Programs |
| `Enrolled_Students` (Multi-Select) | الطلاب المسجلين | BTEC_Students |

---

### 6. Best Practices للاستخدام

#### ✅ DO (افعل)

1. **استخدم الحقول كـ Foreign Keys:**
   ```python
   student = Student(
       moodle_user_id="123",  # ✅ Store Moodle reference
       zoho_id=None          # ✅ Will be filled after sync
   )
   ```

2. **استخدمها للبحث والـ Lookup:**
   ```python
   # ✅ Find by Moodle ID
   student = db.query(Student).filter(
       Student.moodle_user_id == "123"
   ).first()
   ```

3. **استخدمها لمنع التكرار:**
   ```python
   # ✅ Check for existing record
   existing = db.query(Student).filter(
       Student.moodle_user_id == webhook_data['userid']
   ).first()
   ```

4. **خزنها دائماً عند الإنشاء من Moodle:**
   ```python
   # ✅ Always store Moodle reference
   new_student = Student(
       moodle_user_id=str(moodle_data['userid']),  # ✅
       # ...
   )
   ```

5. **أرسلها إلى Zoho عند المزامنة:**
   ```python
   # ✅ Include in Zoho sync
   zoho_data = {
       "Student_Moodle_ID": student.moodle_user_id,  # ✅
       # ...
   }
   ```

#### ❌ DON'T (لا تفعل)

1. **لا تستخدمها كـ Primary Keys:**
   ```python
   # ❌ WRONG - Don't use as PK
   student = Student(
       id=moodle_user_id,  # ❌ Use UUID instead
   )
   ```

2. **لا تعتمد عليها كـ Unique Constraints في كل مكان:**
   ```python
   # ❌ WRONG - May be NULL for Zoho-sourced records
   zoho_id = Column(String, unique=True)  # ❌
   
   # ✅ CORRECT - Allow NULL, unique only when present
   zoho_id = Column(String, unique=True, nullable=True)  # ✅
   ```

3. **لا تنسى التعامل مع NULL:**
   ```python
   # ❌ WRONG - Assumes zoho_id exists
   if student.zoho_id:  # ❌ May be None
       sync_to_zoho(student)
   
   # ✅ CORRECT - Check for NULL
   if student.zoho_id is None:  # ✅
       sync_to_zoho(student)
   ```

4. **لا تخزن بيانات زائدة:**
   ```python
   # ❌ WRONG - Storing full objects
   moodle_user_data = json.dumps(entire_user_object)  # ❌
   
   # ✅ CORRECT - Store only ID
   moodle_user_id = str(user['id'])  # ✅
   ```

---

### 7. ملخص الحقول المفتاحية

#### 🔑 Primary Keys (داخل كل نظام)

| System | Table/Module | Primary Key | Type |
|--------|-------------|-------------|------|
| Moodle | mdl_user | `id` | Integer (Auto) |
| Moodle | mdl_course | `id` | Integer (Auto) |
| Moodle | mdl_grade_grades | `id` | Integer (Auto) |
| Backend | students | `id` | UUID |
| Backend | enrollments | `id` | UUID |
| Backend | grades | `id` | UUID |
| Zoho | BTEC_Students | `Name` | Auto Number |
| Zoho | BTEC_Enrollments | `Name` | Auto Number |
| Zoho | BTEC_Grades | `Name` | Auto Number |

#### 🔗 Foreign Keys (للربط بين الأنظمة)

| System | Table | Foreign Key Field | Links To |
|--------|-------|------------------|----------|
| Backend | students | `moodle_user_id` | Moodle `mdl_user.id` |
| Backend | students | `zoho_id` | Zoho `BTEC_Students.Name` |
| Backend | enrollments | `moodle_user_id` | Moodle `mdl_user.id` |
| Backend | enrollments | `moodle_course_id` | Moodle `mdl_course.id` |
| Backend | enrollments | `zoho_id` | Zoho `BTEC_Enrollments.Name` |
| Backend | grades | `zoho_id` | Zoho `BTEC_Grades.Name` |
| Zoho | BTEC_Students | `Student_Moodle_ID` | Moodle `mdl_user.id` |
| Zoho | BTEC_Enrollments | `Moodle_Course_ID` | Moodle `mdl_course.id` |
| Zoho | BTEC_Grades | `Moodle_Grade_ID` | Moodle `mdl_grade_grades.id` |
| Zoho | BTEC_Classes | `Moodle_Class_ID` | Moodle `mdl_course.id` |

---

### 8. خلاصة الجواب

**السؤال:** هل الحقول يلي فيها كلمة Moodle في Zoho هي حقول مفتاحية؟

**الجواب المفصل:**

✅ **نعم، هي حقول مفتاحية** - لكن بمعنى **Foreign Keys** وليس **Primary Keys**

**الغرض منها:**
1. 🔗 **الربط** - Link records بين Moodle و Zoho
2. 🔍 **البحث** - Lookup records باستخدام Moodle IDs
3. 🆔 **منع التكرار** - Prevent duplicates (Idempotency)
4. 🔄 **المزامنة الثنائية** - Bidirectional sync support
5. 📊 **التتبع** - Track data origin (من Moodle)

**متى تُستخدم:**
- ✅ عند إنشاء سجل من Moodle → Backend
- ✅ عند مزامنة Backend → Zoho
- ✅ عند البحث في Zoho عن سجلات Moodle
- ✅ عند تحديث بيانات من Zoho → Backend → Moodle

**أهميتها:**
- 🔴 **CRITICAL** - بدونها **لا يمكن** ربط السجلات بين الأنظمة
- 🔴 **REQUIRED** - يجب تخزينها **دائماً** عند الإنشاء من Moodle
- 🟡 **INDEXED** - يفضل إضافة فهرس عليها في Zoho لتسريع البحث

---

## 9. Data Population Workflows (سيناريوهات تعبئة الحقول)

### 🔄 Overview: متى ومن وين تتعبى الحقول المفتاحية

**الفكرة الأساسية:**
- الحقول المفتاحية **ما بتتعبى يدوي**
- بتتعبى **أوتوماتيك** عن طريق الكود
- كل حقل إله **مصدر محدد** و**وقت محدد** للتعبئة

---

### 🎯 Scenario 1: User Creation (Moodle → Backend → Zoho)

#### 📌 When: عند إنشاء User جديد في Moodle

**Flow:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                     USER CREATION WORKFLOW                              │
└─────────────────────────────────────────────────────────────────────────┘

Step 1: Admin creates user in Moodle
┌────────────┐
│  MOODLE    │
│            │  New User Created
│ mdl_user   │  - id: 456
│ id = 456   │  - email: ali@example.com
└─────┬──────┘  - role: student/teacher
      │
      │ 🔔 Observer Triggered
      │
      ▼
Step 2: Moodle Observer sends webhook
┌────────────┐
│  OBSERVER  │  POST /api/v1/events/moodle/user_created
│  (Plugin)  │  {
│            │    "userid": 456,
└─────┬──────┘    "email": "ali@example.com",
      │           "firstname": "Ali",
      │           "lastname": "Ahmad",
      │           "role": "student"
      │         }
      │
      ▼
Step 3: Backend receives & stores
┌────────────┐
│  BACKEND   │  student = Student(
│  FastAPI   │    id=UUID(),
│            │    moodle_user_id="456",  ← ✅ Moodle ID stored
└─────┬──────┘    academic_email="ali@example.com",
      │           source="moodle",
      │           zoho_id=None  ← ⏳ Not yet synced
      │         )
      │
      ▼
Step 4: Backend syncs to Zoho
┌────────────┐
│  BACKEND   │  POST to Zoho API
│  Sync Svc  │  {
│            │    "First_Name": "Ali",
└─────┬──────┘    "Last_Name": "Ahmad",
      │           "Academic_Email": "ali@example.com",
      │           "Student_Moodle_ID": "456"  ← ✅ Sent to Zoho
      │         }
      │
      ▼
Step 5: Zoho stores record
┌────────────┐
│   ZOHO     │  BTEC_Students record created:
│   CRM      │  - Name: "STU-0123" (Auto)
│            │  - First_Name: "Ali"
└─────┬──────┘  - Student_Moodle_ID: "456"  ← ✅ Stored in Zoho
      │
      │ Response: { "id": "5398830000456789" }
      │
      ▼
Step 6: Backend updates zoho_id
┌────────────┐
│  BACKEND   │  student.zoho_id = "5398830000456789"  ← ✅ Link complete
│  Database  │  student.sync_status = "synced"
│            │  db.commit()
└────────────┘
```

#### 🔑 Fields Populated:

| Field | System | When | Value | Code Responsible |
|-------|--------|------|-------|------------------|
| `mdl_user.id` | Moodle | Step 1 | `456` | Moodle auto-increment |
| `students.moodle_user_id` | Backend | Step 3 | `"456"` | `moodle_events.py` endpoint |
| `Student_Moodle_ID` | Zoho | Step 5 | `"456"` | Backend Sync Service |
| `students.zoho_id` | Backend | Step 6 | `"5398830..."` | Backend Sync Service |
| `BTEC_Students.Name` | Zoho | Step 5 | `"STU-0123"` | Zoho auto-number |

#### 📝 Code Implementation:

**Step 2: Moodle Plugin Observer (PHP)**
```php
// local/moodle_zoho_sync/classes/observer.php
class observer {
    public static function user_created(\core\event\user_created $event) {
        $user = $event->get_record_snapshot('user', $event->objectid);
        
        // Prepare data
        $data = [
            'userid' => $user->id,  // ← This becomes Student_Moodle_ID
            'username' => $user->username,
            'email' => $user->email,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
        ];
        
        // Send webhook to Backend
        $webhook_url = get_config('local_moodle_zoho_sync', 'backend_url') 
                     . '/api/v1/events/moodle/user_created';
        
        self::send_webhook($webhook_url, $data);
    }
}
```

**Step 3: Backend Webhook Handler (Python)**
```python
# app/api/v1/endpoints/moodle_events.py
@router.post("/user_created")
async def handle_user_created(event: MoodleUserEvent, db: Session = Depends(get_db)):
    # Check if exists
    existing = db.query(Student).filter(
        Student.moodle_user_id == str(event.userid)
    ).first()
    
    if existing:
        # Update existing
        existing.academic_email = event.email
        existing.updated_at = datetime.now()
    else:
        # Create new with Moodle ID
        student = Student(
            id=uuid4(),
            moodle_user_id=str(event.userid),  # ← ✅ Store Moodle ID
            academic_email=event.email,
            display_name=f"{event.firstname} {event.lastname}",
            source="moodle",
            zoho_id=None,  # Will be filled after Zoho sync
            sync_status="pending"
        )
        db.add(student)
    
    db.commit()
    return {"status": "success"}
```

**Step 4: Backend Sync to Zoho (Python)**
```python
# app/services/zoho_sync_service.py (FUTURE IMPLEMENTATION)
async def sync_students_to_zoho():
    # Find students pending sync
    students = db.query(Student).filter(
        Student.source == "moodle",
        Student.sync_status == "pending",
        Student.zoho_id.is_(None)
    ).all()
    
    for student in students:
        # Prepare Zoho data
        zoho_data = {
            "First_Name": student.display_name.split()[0],
            "Last_Name": student.display_name.split()[-1],
            "Academic_Email": student.academic_email,
            "Phone_Number": student.phone or "",
            "Student_Moodle_ID": student.moodle_user_id,  # ← ✅ Send to Zoho
        }
        
        # POST to Zoho API
        response = await zoho_api.create_record("BTEC_Students", zoho_data)
        
        if response.status_code == 201:
            # Update student with Zoho ID
            student.zoho_id = response.json()['data'][0]['details']['id']
            student.sync_status = "synced"
            student.last_synced_at = datetime.now()
            db.commit()
```

---

### 🎯 Scenario 2: Grade Submission (Moodle → Backend → Zoho)

#### 📌 When: عند تصحيح درجات في Moodle

**Flow:**
```
Step 1: Teacher submits grade in Moodle
┌────────────┐
│  MOODLE    │  Grade submitted:
│            │  - grade_id: 789
│ mdl_grade  │  - userid: 456 (Ali)
│  _grades   │  - itemid: 123 (Unit 1)
└─────┬──────┘  - finalgrade: 85.5
      │
      ▼
Step 2: Moodle Observer triggers
┌────────────┐
│  OBSERVER  │  POST /api/v1/events/moodle/grade_updated
│            │  {
└─────┬──────┘    "grade_id": 789,
      │           "userid": 456,
      │           "itemid": 123,
      │           "finalgrade": 85.5
      │         }
      │
      ▼
Step 3: Backend converts & stores
┌────────────┐
│  BACKEND   │  grade = Grade(
│            │    moodle_grade_id=789,  ← ✅ Store Moodle Grade ID
└─────┬──────┘    moodle_user_id=456,   ← ✅ Link to student
      │           moodle_item_id=123,
      │           score=85.5,
      │           grade_value="Distinction",  ← BTEC conversion
      │           composite_key="456_123"    ← Prevent duplicates
      │         )
      │
      ▼
Step 4: Sync to Zoho
┌────────────┐
│  ZOHO      │  BTEC_Grades record:
│            │  - Moodle_Grade_ID: "789"              ← ✅
└────────────┘  - Moodle_Grade_Composite_Key: "456_123" ← ✅
                - Grade: "Distinction"
                - Student: (lookup by Student_Moodle_ID = 456)
```

#### 🔑 Fields Populated:

| Field | System | Value | Purpose |
|-------|--------|-------|---------|
| `Moodle_Grade_ID` | Zoho | `"789"` | رقم الدرجة الفريد من Moodle |
| `Moodle_Grade_Composite_Key` | Zoho | `"456_123"` | منع تكرار الدرجة (user+item) |
| `Student` (Lookup) | Zoho | Find by `Student_Moodle_ID=456` | ربط الدرجة بالطالب |

#### 📝 Code:

```python
# app/api/v1/endpoints/moodle_events.py
@router.post("/grade_updated")
async def handle_grade_updated(event: MoodleGradeEvent, db: Session = Depends(get_db)):
    # Convert to BTEC grade
    btec_grade = convert_moodle_grade(event.finalgrade)
    
    # Create composite key (prevent duplicates)
    composite_key = f"{event.userid}_{event.itemid}"
    
    # Check if grade exists
    existing = db.query(Grade).filter(
        Grade.composite_key == composite_key
    ).first()
    
    if existing:
        existing.score = event.finalgrade
        existing.grade_value = btec_grade
    else:
        grade = Grade(
            id=uuid4(),
            moodle_grade_id=event.grade_id,        # ← ✅ Store Moodle Grade ID
            moodle_user_id=event.userid,           # ← ✅ Link to student
            moodle_item_id=event.itemid,
            composite_key=composite_key,           # ← ✅ For uniqueness
            score=event.finalgrade,
            grade_value=btec_grade,
            sync_status="pending"
        )
        db.add(grade)
    
    db.commit()
```

---

### 🎯 Scenario 3: Course Creation (Zoho → Backend → Moodle)

#### 📌 When: عند إنشاء Course جديد في Zoho

**Flow:**
```
Step 1: Admin creates course in Zoho
┌────────────┐
│   ZOHO     │  BTEC_Programs record created:
│            │  - Name: "PROG-001"
│            │  - Program_Name: "Business Management"
└─────┬──────┘  - Moodle_ID: NULL  ← ⏳ Not yet in Moodle
      │
      │ 🔔 Webhook triggered
      │
      ▼
Step 2: Zoho sends webhook to Backend
┌────────────┐
│   ZOHO     │  POST /api/v1/events/zoho/program
│  Webhook   │  {
└─────┬──────┘    "record_id": "5398830000789012",
      │           "operation": "insert",
      │           "data": {
      │             "Name": "PROG-001",
      │             "Program_Name": "Business Management"
      │           }
      │         }
      │
      ▼
Step 3: Backend calls Moodle API to create course
┌────────────┐
│  BACKEND   │  POST to Moodle Web Service API
│ Moodle API │  core_course_create_courses([{
│  Client    │    "fullname": "Business Management",
└─────┬──────┘    "shortname": "BM001",
      │           "categoryid": 1
      │         }])
      │
      │ Response: [{ "id": 999, "shortname": "BM001" }]
      │
      ▼
Step 4: Backend stores Moodle Course ID
┌────────────┐
│  BACKEND   │  program = Program(
│  Database  │    zoho_id="5398830000789012",
└─────┬──────┘    moodle_course_id=999,  ← ✅ Store Moodle Course ID
      │           name="Business Management"
      │         )
      │
      ▼
Step 5: Backend updates Zoho with Moodle ID
┌────────────┐
│  BACKEND   │  PUT to Zoho API
│ Zoho Sync  │  {
└─────┬──────┘    "id": "5398830000789012",
      │           "crmnmoodle__Moodle_ID": "999"  ← ✅ Update Zoho
      │         }
      │
      ▼
Step 6: Zoho record updated
┌────────────┐
│   ZOHO     │  BTEC_Programs:
│            │  - Name: "PROG-001"
└────────────┘  - crmnmoodle__Moodle_ID: "999"  ← ✅ Link complete
```

#### 🔑 Fields Populated:

| Field | System | When | Value | Code Responsible |
|-------|--------|------|-------|------------------|
| `BTEC_Programs.Name` | Zoho | Step 1 | `"PROG-001"` | Zoho auto-number |
| `mdl_course.id` | Moodle | Step 3 | `999` | Moodle API |
| `programs.moodle_course_id` | Backend | Step 4 | `999` | Backend Moodle API client |
| `crmnmoodle__Moodle_ID` | Zoho | Step 5 | `"999"` | Backend Sync Service |

#### 📝 Code Implementation:

**Step 3: Backend calls Moodle API**
```python
# app/services/moodle_api_service.py (FUTURE IMPLEMENTATION)
async def create_course_in_moodle(program_data: dict) -> int:
    """Create course in Moodle and return course ID"""
    
    # Prepare Moodle course data
    course_data = {
        "fullname": program_data['Program_Name'],
        "shortname": program_data['Name'],
        "categoryid": 1,  # Default category
        "summary": program_data.get('Description', ''),
        "format": "topics"
    }
    
    # Call Moodle Web Service API
    response = await moodle_api.call_function(
        "core_course_create_courses",
        courses=[course_data]
    )
    
    moodle_course_id = response[0]['id']  # ← ✅ Get Moodle Course ID
    return moodle_course_id
```

**Step 4-5: Store and sync back to Zoho**
```python
# app/api/v1/endpoints/events.py - Zoho program webhook handler
@router.post("/zoho/program")
async def handle_zoho_program(event: ZohoWebhook, db: Session = Depends(get_db)):
    if event.operation == "insert":
        # Create course in Moodle first
        moodle_course_id = await create_course_in_moodle(event.data)
        
        # Store in Backend
        program = Program(
            id=uuid4(),
            zoho_id=event.record_id,
            moodle_course_id=moodle_course_id,  # ← ✅ Store Moodle ID
            name=event.data['Program_Name'],
            source="zoho",
            sync_status="synced"
        )
        db.add(program)
        db.commit()
        
        # Update Zoho with Moodle Course ID
        await zoho_api.update_record(
            module="BTEC_Programs",
            record_id=event.record_id,
            data={
                "crmnmoodle__Moodle_ID": str(moodle_course_id)  # ← ✅ Send back
            }
        )
```

---

### 🎯 Scenario 4: Enrollment Creation (Zoho → Backend → Moodle)

#### 📌 When: عند تسجيل طالب في Course من Zoho

**Flow:**
```
Step 1: Admin enrolls student in Zoho
┌────────────┐
│   ZOHO     │  BTEC_Enrollments:
│            │  - Student: (lookup to STU-001)
└─────┬──────┘  - Class: (lookup to BM001)
      │         - Moodle_Course_ID: NULL
      │
      ▼
Step 2: Backend receives webhook
      ↓
Step 3: Backend enrolls in Moodle
┌────────────┐
│  BACKEND   │  Call Moodle API:
│            │  enrol_manual_enrol_users({
└─────┬──────┘    "userid": 456,  ← من Student_Moodle_ID
      │           "courseid": 999  ← من crmnmoodle__Moodle_ID
      │         })
      │
      │ Response: { "enrolment_id": 1234 }
      │
      ▼
Step 4: Store enrollment IDs
┌────────────┐
│  BACKEND   │  enrollment = Enrollment(
│            │    moodle_enrollment_id=1234,  ← ✅
└─────┬──────┘    moodle_user_id=456,        ← ✅
      │           moodle_course_id=999        ← ✅
      │         )
      │
      ▼
Step 5: Update Zoho
┌────────────┐
│   ZOHO     │  BTEC_Enrollments:
│            │  - Moodle_Course_ID: "999"  ← ✅
└────────────┘
```

#### 🔑 Fields Populated:

| Field | System | Value | Purpose |
|-------|--------|-------|---------|
| `moodle_enrollment_id` | Backend | `1234` | رقم التسجيل من Moodle |
| `moodle_user_id` | Backend | `456` | Student Moodle ID |
| `moodle_course_id` | Backend | `999` | Course Moodle ID |
| `Moodle_Course_ID` | Zoho | `"999"` | ربط التسجيل بالكورس |

---

### 📊 Summary: Field Population Matrix

| Field | Source System | Target System | Populated When | Populated By |
|-------|--------------|---------------|----------------|--------------|
| **Student_Moodle_ID** | Moodle | Backend → Zoho | User created in Moodle | Moodle Observer → Backend Webhook |
| **Teacher_Moodle_ID** | Moodle | Backend → Zoho | Teacher created in Moodle | Moodle Observer → Backend Webhook |
| **Moodle_Grade_ID** | Moodle | Backend → Zoho | Grade submitted in Moodle | Moodle Observer → Backend Webhook |
| **Moodle_Grade_Composite_Key** | Backend | Backend → Zoho | Grade stored in Backend | Backend (generated: userid_itemid) |
| **crmnmoodle__Moodle_ID** | Moodle API | Zoho | Course created in Moodle | Backend Moodle API Client → Zoho Update |
| **Moodle_Class_ID** | Moodle API | Zoho | Class/Course created | Backend Moodle API Client → Zoho Update |
| **Moodle_Course_ID** (Enrollment) | Moodle API | Zoho | Enrollment created | Backend Moodle API Client → Zoho Update |
| **Last_Sync_with_Moodle** | Backend | Zoho | Any sync operation | Backend Sync Service (timestamp) |

---

### 🎯 Scenario 5: Unit Creation/Update (Zoho → Backend → Moodle)

#### 📌 When: عند إنشاء أو تعديل Unit في Zoho

**Context:** Units في Zoho = Grade Items في Moodle

**Flow:**
```
Step 1: Admin creates/updates Unit in Zoho
┌────────────┐
│   ZOHO     │  BTEC_Units record:
│            │  - Name: "UNIT-001"
│            │  - Unit_Name: "Business Environment"
└─────┬──────┘  - Last_Sync_with_Moodle: NULL  ← ⏳
      │         - Moodle_Grading_Template: NULL ← ⏳
      │
      │ 🔔 Webhook triggered (insert/update)
      │
      ▼
Step 2: Zoho webhook → Backend
┌────────────┐
│   ZOHO     │  POST /api/v1/events/zoho/unit
│  Webhook   │  {
└─────┬──────┘    "notification_id": "notify_123",
      │           "operation": "insert",
      │           "module": "BTEC_Units",
      │           "record_id": "5398830001111111",
      │           "data": {
      │             "Name": "UNIT-001",
      │             "Unit_Name": "Business Environment",
      │             "BTEC_Program": "5398830000789012"
      │           }
      │         }
      │
      ▼
Step 3: Backend creates grade item in Moodle
┌────────────┐
│  BACKEND   │  Call Moodle Web Service:
│ Moodle API │  core_grades_create_gradecategories([{
│  Client    │    "courseid": 999,  ← من Program lookup
└─────┬──────┘    "fullname": "Business Environment",
      │           "grademax": 100
      │         }])
      │
      │ Response: { "categoryid": 777 }
      │
      ▼
Step 4: Backend stores unit
┌────────────┐
│  BACKEND   │  unit = Unit(
│  Database  │    zoho_id="5398830001111111",
└─────┬──────┘    moodle_category_id=777,
      │           name="Business Environment"
      │         )
      │
      ▼
Step 5: Backend updates Zoho with sync info
┌────────────┐
│  BACKEND   │  PUT to Zoho API
│ Zoho Sync  │  {
└─────┬──────┘    "id": "5398830001111111",
      │           "Moodle_Grading_Template": "777",  ← ✅
      │           "Last_Sync_with_Moodle": "2026-01-26T14:30:00Z"  ← ✅
      │         }
      │
      ▼
Step 6: Zoho record updated with sync status
┌────────────┐
│   ZOHO     │  BTEC_Units:
│            │  - Name: "UNIT-001"
│            │  - Moodle_Grading_Template: "777"  ← ✅ Synced
└────────────┘  - Last_Sync_with_Moodle: "2026-01-26T14:30:00Z"  ← ✅
```

#### 🔑 Sync Fields Populated:

| Field | Type | Value | When | Purpose |
|-------|------|-------|------|---------|
| `Moodle_Grading_Template` | Text | `"777"` | After Moodle creation | رقم الـ category في Moodle |
| `Last_Sync_with_Moodle` | DateTime | `"2026-01-26T14:30:00Z"` | After successful sync | آخر وقت تمت المزامنة |
| `Sync_Status` (if exists) | Picklist | `"Synced"` | After successful sync | حالة المزامنة |

#### 📝 Code Implementation:

**Step 2-3: Backend webhook handler**
```python
# app/api/v1/endpoints/events.py
@router.post("/zoho/unit")
async def handle_zoho_unit(event: ZohoWebhook, db: Session = Depends(get_db)):
    """Handle Unit creation/update from Zoho"""
    
    try:
        if event.operation in ["insert", "update"]:
            # Get program Moodle ID
            program = db.query(Program).filter(
                Program.zoho_id == event.data['BTEC_Program']
            ).first()
            
            if not program or not program.moodle_course_id:
                return {"status": "error", "message": "Program not synced to Moodle"}
            
            # Create/Update grade category in Moodle
            if event.operation == "insert":
                moodle_response = await moodle_api.create_grade_category(
                    courseid=program.moodle_course_id,
                    fullname=event.data['Unit_Name'],
                    grademax=100
                )
                category_id = moodle_response['categoryid']
                
                # Store in Backend
                unit = Unit(
                    id=uuid4(),
                    zoho_id=event.record_id,
                    moodle_category_id=category_id,
                    name=event.data['Unit_Name'],
                    source="zoho"
                )
                db.add(unit)
            else:  # update
                unit = db.query(Unit).filter(
                    Unit.zoho_id == event.record_id
                ).first()
                
                if unit and unit.moodle_category_id:
                    await moodle_api.update_grade_category(
                        categoryid=unit.moodle_category_id,
                        fullname=event.data['Unit_Name']
                    )
            
            db.commit()
            
            # Update Zoho with sync info
            sync_timestamp = datetime.now(timezone.utc).isoformat()
            await zoho_api.update_record(
                module="BTEC_Units",
                record_id=event.record_id,
                data={
                    "Moodle_Grading_Template": str(unit.moodle_category_id),  # ← ✅
                    "Last_Sync_with_Moodle": sync_timestamp  # ← ✅
                }
            )
            
            return {
                "status": "success",
                "message": "Unit synced to Moodle",
                "moodle_category_id": unit.moodle_category_id,
                "sync_timestamp": sync_timestamp
            }
    
    except Exception as e:
        logger.error(f"Error syncing unit: {e}")
        
        # Update Zoho with error status
        await zoho_api.update_record(
            module="BTEC_Units",
            record_id=event.record_id,
            data={
                "Last_Sync_with_Moodle": datetime.now(timezone.utc).isoformat(),
                "Sync_Status": "Failed",  # If field exists
                "Sync_Error": str(e)[:250]  # If field exists
            }
        )
        
        return {"status": "error", "message": str(e)}
```

---

### 🎯 Scenario 6: Program/Course Update (Zoho → Backend → Moodle)

#### 📌 When: عند تعديل Program في Zoho بعد ما يكون موجود في Moodle

**Flow:**
```
Step 1: Admin updates Program in Zoho
┌────────────┐
│   ZOHO     │  BTEC_Programs:
│            │  - Name: "PROG-001"
│            │  - crmnmoodle__Moodle_ID: "999"  ← Already synced
└─────┬──────┘  - Program_Name: "Business Management Level 5"  ← UPDATED
      │
      │ 🔔 Webhook: operation = "update"
      │
      ▼
Step 2: Backend receives update
      ↓
Step 3: Backend updates Moodle course
┌────────────┐
│  BACKEND   │  Call Moodle API:
│            │  core_course_update_courses([{
└─────┬──────┘    "id": 999,
      │           "fullname": "Business Management Level 5"
      │         }])
      │
      ▼
Step 4: Backend confirms sync back to Zoho
┌────────────┐
│  BACKEND   │  PUT to Zoho:
│            │  {
└─────┬──────┘    "Last_Updated_in_Moodle": "2026-01-26T15:00:00Z"  ← ✅
      │         }
      │
      ▼
Step 5: Zoho updated with timestamp
┌────────────┐
│   ZOHO     │  BTEC_Programs:
│            │  - Last_Updated_in_Moodle: "2026-01-26T15:00:00Z"  ← ✅
└────────────┘
```

#### 🔑 Sync Fields:

| Zoho Module | Sync Field Name | Type | Purpose |
|-------------|----------------|------|---------|
| BTEC_Programs | `Last_Updated_in_Moodle` | DateTime | آخر تحديث في Moodle |
| BTEC_Programs | `crmnmoodle__Moodle_ID` | Text | رقم الكورس في Moodle |

---

### 🎯 Scenario 7: Registration (Zoho → Backend → Moodle Enrollment)

#### 📌 When: عند تسجيل طالب جديد في Registration

**Context:** Registration في Zoho = يصير create في Enrollment → يصير enrol في Moodle

**Flow:**
```
Step 1: Student/Admin creates Registration in Zoho
┌────────────┐
│   ZOHO     │  BTEC_Registrations:
│            │  - Name: "REG-2026-001"
│            │  - Student: (lookup to STU-001)
└─────┬──────┘  - Program: (lookup to PROG-001)
      │         - Moodle_Sync_Status: "Pending"  ← ⏳
      │
      │ 🔔 Webhook triggered
      │
      ▼
Step 2: Backend creates Enrollment
┌────────────┐
│  BACKEND   │  enrollment = Enrollment(
│            │    student_id=...,  ← من Student lookup
└─────┬──────┘    program_id=...,  ← من Program lookup
      │           source="zoho"
      │         )
      │
      ▼
Step 3: Backend enrolls in Moodle
┌────────────┐
│  BACKEND   │  Call Moodle API:
│ Moodle API │  enrol_manual_enrol_users([{
└─────┬──────┘    "userid": 456,  ← Student_Moodle_ID
      │           "courseid": 999,  ← crmnmoodle__Moodle_ID
      │           "roleid": 5  ← Student role
      │         }])
      │
      │ Response: Success (no ID returned for enrollments)
      │
      ▼
Step 4: Backend updates Zoho Registration
┌────────────┐
│  BACKEND   │  PUT to Zoho:
│            │  {
└─────┬──────┘    "Moodle_Sync_Status": "Synced",  ← ✅
      │           "Moodle_Sync_Date": "2026-01-26T16:00:00Z",  ← ✅
      │           "Moodle_Course_ID": "999",  ← ✅
      │           "Student_Moodle_ID": "456"  ← ✅ (if not present)
      │         }
      │
      ▼
Step 5: Zoho Registration updated
┌────────────┐
│   ZOHO     │  BTEC_Registrations:
│            │  - Moodle_Sync_Status: "Synced"  ← ✅
│            │  - Moodle_Sync_Date: "2026-01-26T16:00:00Z"  ← ✅
└────────────┘  - Moodle_Course_ID: "999"
```

#### 🔑 Sync Fields in BTEC_Registrations:

| Field | Type | Value | Purpose |
|-------|------|-------|---------|
| `Moodle_Sync_Status` | Picklist | `"Synced"` / `"Pending"` / `"Failed"` | حالة المزامنة |
| `Moodle_Sync_Date` | DateTime | `"2026-01-26T16:00:00Z"` | تاريخ المزامنة |
| `Moodle_Course_ID` | Text | `"999"` | رقم الكورس اللي تم التسجيل فيه |
| `Student_Moodle_ID` | Text | `"456"` | (Copy from Student) للربط |

#### 📝 Code:

```python
# app/api/v1/endpoints/events.py
@router.post("/zoho/registration")
async def handle_zoho_registration(event: ZohoWebhook, db: Session = Depends(get_db)):
    """Handle Registration creation from Zoho"""
    
    try:
        if event.operation == "insert":
            # Get student with Moodle ID
            student_zoho_id = event.data['Student']  # Lookup field
            student = db.query(Student).filter(
                Student.zoho_id == student_zoho_id
            ).first()
            
            if not student or not student.moodle_user_id:
                raise ValueError("Student not synced to Moodle")
            
            # Get program with Moodle Course ID
            program_zoho_id = event.data['Program']
            program = db.query(Program).filter(
                Program.zoho_id == program_zoho_id
            ).first()
            
            if not program or not program.moodle_course_id:
                raise ValueError("Program not synced to Moodle")
            
            # Enroll in Moodle
            await moodle_api.enrol_user(
                userid=int(student.moodle_user_id),
                courseid=int(program.moodle_course_id),
                roleid=5  # Student role
            )
            
            # Create enrollment in Backend
            enrollment = Enrollment(
                id=uuid4(),
                zoho_id=event.record_id,
                student_id=student.id,
                program_id=program.id,
                moodle_user_id=int(student.moodle_user_id),
                moodle_course_id=str(program.moodle_course_id),
                source="zoho",
                sync_status="synced"
            )
            db.add(enrollment)
            db.commit()
            
            # Update Zoho with sync status
            sync_timestamp = datetime.now(timezone.utc).isoformat()
            await zoho_api.update_record(
                module="BTEC_Registrations",
                record_id=event.record_id,
                data={
                    "Moodle_Sync_Status": "Synced",  # ← ✅
                    "Moodle_Sync_Date": sync_timestamp,  # ← ✅
                    "Moodle_Course_ID": str(program.moodle_course_id),  # ← ✅
                    "Student_Moodle_ID": student.moodle_user_id  # ← ✅
                }
            )
            
            return {
                "status": "success",
                "message": "Student enrolled in Moodle",
                "moodle_user_id": student.moodle_user_id,
                "moodle_course_id": program.moodle_course_id,
                "sync_timestamp": sync_timestamp
            }
    
    except Exception as e:
        logger.error(f"Error enrolling student: {e}")
        
        # Update Zoho with error
        await zoho_api.update_record(
            module="BTEC_Registrations",
            record_id=event.record_id,
            data={
                "Moodle_Sync_Status": "Failed",  # ← ✅
                "Moodle_Sync_Date": datetime.now(timezone.utc).isoformat(),
                "Sync_Error_Message": str(e)[:250]
            }
        )
        
        return {"status": "error", "message": str(e)}
```

---

### 🎯 Scenario 8: Class Creation (Zoho → Backend → Moodle)

#### 📌 When: عند إنشاء Class جديد في Zoho

**Context:** Class في Zoho = Course في Moodle (نسخة من Program للطلاب)

**Flow:**
```
Step 1: Admin creates Class
┌────────────┐
│   ZOHO     │  BTEC_Classes:
│            │  - Name: "CLASS-BM-2026-A"
│            │  - Class_Name: "Business Management 2026 Section A"
└─────┬──────┘  - BTEC_Program: (lookup to PROG-001)
      │         - Moodle_Class_ID: NULL  ← ⏳
      │
      ▼
Step 2: Backend creates course in Moodle
┌────────────┐
│  BACKEND   │  Create Moodle course
│            │  Response: { "id": 1001 }
└─────┬──────┘
      │
      ▼
Step 3: Update Zoho with Moodle Class ID
┌────────────┐
│   ZOHO     │  BTEC_Classes:
│            │  - Moodle_Class_ID: "1001"  ← ✅
└────────────┘  - Last_Synced_to_Moodle: "2026-01-26T17:00:00Z"  ← ✅
```

#### 🔑 Sync Fields in BTEC_Classes:

| Field | Type | Purpose |
|-------|------|---------|
| `Moodle_Class_ID` | Text | رقم الكورس في Moodle |
| `Last_Synced_to_Moodle` | DateTime | آخر مزامنة |

---

### 📊 Complete Sync Fields Matrix (All Modules)

| Zoho Module | Sync Field(s) | Type | Populated When | Value Example |
|-------------|--------------|------|----------------|---------------|
| **BTEC_Students** | `Student_Moodle_ID` | Text | User created in Moodle | `"456"` |
| **BTEC_Teachers** | `Teacher_Moodle_ID` | Text | Teacher created in Moodle | `"789"` |
| **BTEC_Programs** | `crmnmoodle__Moodle_ID` | Text | Course created in Moodle | `"999"` |
| **BTEC_Programs** | `Last_Updated_in_Moodle` | DateTime | Course updated in Moodle | `"2026-01-26T15:00:00Z"` |
| **BTEC_Classes** | `Moodle_Class_ID` | Text | Class/Course created | `"1001"` |
| **BTEC_Classes** | `Last_Synced_to_Moodle` | DateTime | After successful sync | `"2026-01-26T17:00:00Z"` |
| **BTEC_Units** | `Moodle_Grading_Template` | Text | Grade category created | `"777"` |
| **BTEC_Units** | `Last_Sync_with_Moodle` | DateTime | After successful sync | `"2026-01-26T14:30:00Z"` |
| **BTEC_Enrollments** | `Moodle_Course_ID` | Text | Enrollment created | `"999"` |
| **BTEC_Grades** | `Moodle_Grade_ID` | Text | Grade submitted in Moodle | `"789"` |
| **BTEC_Grades** | `Moodle_Grade_Composite_Key` | Text | Grade stored | `"456_123"` |
| **BTEC_Registrations** | `Moodle_Sync_Status` | Picklist | After sync attempt | `"Synced"` / `"Failed"` |
| **BTEC_Registrations** | `Moodle_Sync_Date` | DateTime | After sync | `"2026-01-26T16:00:00Z"` |
| **BTEC_Registrations** | `Moodle_Course_ID` | Text | After enrollment | `"999"` |
| **BTEC_Registrations** | `Student_Moodle_ID` | Text | Copy from Student | `"456"` |

---

### 🔄 Sync Response Pattern (Backend → Zoho)

#### ✅ Success Response:

```python
# After successful sync to Moodle
await zoho_api.update_record(
    module=module_name,
    record_id=zoho_record_id,
    data={
        # Primary Moodle ID field
        "Moodle_[Entity]_ID": str(moodle_id),
        
        # Timestamp field
        "Last_Sync_with_Moodle": datetime.now(timezone.utc).isoformat(),
        # OR
        "Last_Synced_to_Moodle": datetime.now(timezone.utc).isoformat(),
        # OR
        "Last_Updated_in_Moodle": datetime.now(timezone.utc).isoformat(),
        
        # Status field (if exists)
        "Moodle_Sync_Status": "Synced",
        "Sync_Status": "Synced"
    }
)
```

#### ❌ Error Response:

```python
# After failed sync
await zoho_api.update_record(
    module=module_name,
    record_id=zoho_record_id,
    data={
        # Timestamp (still update)
        "Last_Sync_with_Moodle": datetime.now(timezone.utc).isoformat(),
        
        # Status
        "Moodle_Sync_Status": "Failed",
        "Sync_Status": "Failed",
        
        # Error details (if field exists)
        "Sync_Error_Message": error_message[:250],
        "Last_Sync_Error": error_message[:250]
    }
)
```

---

### 🎯 Naming Patterns for Sync Fields

#### Pattern 1: Moodle ID Storage
```
Format: [Entity]_Moodle_ID or Moodle_[Entity]_ID
Examples:
- Student_Moodle_ID
- Teacher_Moodle_ID
- Moodle_Class_ID
- Moodle_Course_ID
- Moodle_Grade_ID
```

#### Pattern 2: Timestamp Fields
```
Format: Last_[Action]_[with/to/in]_Moodle
Examples:
- Last_Sync_with_Moodle
- Last_Synced_to_Moodle
- Last_Updated_in_Moodle
```

#### Pattern 3: Status Fields
```
Format: [Scope]_Sync_Status or Moodle_Sync_Status
Examples:
- Moodle_Sync_Status
- Sync_Status
Values: "Pending", "Synced", "Failed"
```

#### Pattern 4: Composite/Special Fields
```
Examples:
- Moodle_Grade_Composite_Key  (Format: "userid_itemid")
- Moodle_Grading_Template  (Grade category ID)
- crmnmoodle__Moodle_ID  (CRM plugin format)
```

---

### 🔧 Implementation Status

| Component | Status | Location |
|-----------|--------|----------|
| **Moodle → Backend Webhooks** | ✅ Implemented | `app/api/v1/endpoints/moodle_events.py` |
| **Backend → Zoho Sync** | ⏳ Pending | `app/services/zoho_sync_service.py` (TODO) |
| **Zoho → Backend Webhooks** | ✅ Implemented | `app/api/v1/endpoints/events.py` |
| **Backend → Moodle API** | ⏳ Pending | `app/services/moodle_api_service.py` (TODO) |
| **Backend → Zoho Sync Response** | ⏳ Pending | Need to implement update callbacks |
| **Moodle Plugin (Observer)** | ⏳ Pending | See `MOODLE_PLUGIN_ARCHITECTURE_AR.md` |

---

### 🎯 Key Takeaways (الخلاصة)

**1. الحقول تتعبى أوتوماتيك:**
- ✅ **ما في تعبئة يدوية** - كلشي عن طريق الكود
- ✅ كل حقل إله **مصدر واضح** (Moodle أو Zoho أو Backend)
- ✅ كل حقل إله **وقت محدد** للتعبئة (creation, update, sync)

**2. الـ Flow ثنائي الاتجاه:**
```
Moodle → Backend → Zoho  (Students, Teachers, Grades)
Zoho → Backend → Moodle  (Programs, Enrollments)
```

**3. الحقول المفتاحية أساسية للربط:**
- بدون `Student_Moodle_ID` → ما بنعرف نربط الطالب بين الأنظمة
- بدون `Moodle_Course_ID` → ما بنعرف نربط التسجيل بالكورس
- بدون `Moodle_Grade_Composite_Key` → ممكن نكرر الدرجة

**4. الكود المسؤول:**
- **Moodle Plugin** → يرسل Moodle IDs عن طريق Webhooks
- **Backend Webhooks** → يستقبل ويخزن الـ IDs
- **Backend Sync Service** → يرسل الـ IDs لـ Zoho
- **Backend Moodle API** → يخزن Moodle IDs اللي يرجعوا من Moodle API

---

**Last Updated:** January 26, 2026  
**Version:** 2.2 - Added Data Population Workflows  
**Maintainer:** Development Team

