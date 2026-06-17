#include "tracker_db.h"
#include "logger.h"
#include <sstream>
#include <iomanip>
#include <mysql/mysql.h>

DatabaseTracker::DatabaseTracker() : conn(nullptr) {}

DatabaseTracker::~DatabaseTracker() {
    if (conn) {
        mysql_close(conn);
    }
}

bool DatabaseTracker::init(const std::string& h, const std::string& u, 
                            const std::string& p, const std::string& db) {
    host = h;
    user = u;
    password = p;
    dbname = db;
    
    return connect();
}

bool DatabaseTracker::connect() {
    conn = mysql_init(nullptr);
    if (!conn) {
        logger.error("Tracker MySQL init failed");
        return false;
    }
    
    if (!mysql_real_connect(conn, host.c_str(), user.c_str(), password.c_str(), 
                            dbname.c_str(), 3306, nullptr, 0)) {
        logger.error("Tracker MySQL connect failed: " + std::string(mysql_error(conn)));
        return false;
    }
    
    logger.info("✅ Tracker MySQL Connected");
    return true;
}

std::string DatabaseTracker::type_to_string(ViolationType type) {
    switch(type) {
        case VIOLATION_DISK_OVER: return "disk_over";
        case VIOLATION_FILE_FLOOD: return "file_flood";
        case VIOLATION_ILLEGAL_FILE: return "illegal_file";
        case VIOLATION_ILLEGAL_PROCESS: return "illegal_process";
        case VIOLATION_CPU_ABUSE: return "cpu_abuse";
        case VIOLATION_RAM_ABUSE: return "ram_abuse";
        default: return "unknown";
    }
}

ViolationType DatabaseTracker::string_to_type(const std::string& str) {
    if (str == "disk_over") return VIOLATION_DISK_OVER;
    if (str == "file_flood") return VIOLATION_FILE_FLOOD;
    if (str == "illegal_file") return VIOLATION_ILLEGAL_FILE;
    if (str == "illegal_process") return VIOLATION_ILLEGAL_PROCESS;
    if (str == "cpu_abuse") return VIOLATION_CPU_ABUSE;
    if (str == "ram_abuse") return VIOLATION_RAM_ABUSE;
    return VIOLATION_DISK_OVER;
}

bool DatabaseTracker::record_violation(int user_id, const std::string& username,
                                        int server_id, const std::string& server_uuid,
                                        const std::string& server_name,
                                        ViolationType type, const std::string& details,
                                        const std::string& file_name, long long file_size,
                                        double disk_usage, int file_count,
                                        const std::string& action, int severity) {
    
    std::ostringstream query;
    query << "INSERT INTO user_violations "
          << "(user_id, username, server_id, server_uuid, server_name, "
          << "violation_type, details, file_name, file_size, disk_usage_gb, "
          << "file_count, action_taken, severity) VALUES ("
          << user_id << ", '"
          << username << "', "
          << server_id << ", '"
          << server_uuid << "', '"
          << server_name << "', '"
          << type_to_string(type) << "', '"
          << details << "', '"
          << file_name << "', "
          << file_size << ", "
          << disk_usage << ", "
          << file_count << ", '"
          << action << "', "
          << severity << ")";
    
    if (mysql_query(conn, query.str().c_str())) {
        logger.error("Failed to record violation: " + std::string(mysql_error(conn)));
        return false;
    }
    
    logger.info("📝 Violation recorded for user " + std::to_string(user_id));
    return true;
}

bool DatabaseTracker::record_simple_violation(int user_id, ViolationType type, 
                                                const std::string& details,
                                                const std::string& action) {
    return record_violation(user_id, "", 0, "", "", type, details, "", 0, 0, 0, action, 1);
}

UserStats DatabaseTracker::get_user_stats(int user_id) {
    UserStats stats;
    stats.user_id = user_id;
    stats.total_violations = 0;
    stats.disk_violations = 0;
    stats.flood_violations = 0;
    stats.illegal_files = 0;
    stats.illegal_processes = 0;
    stats.total_severity = 0;
    stats.is_blacklisted = false;
    
    // Get user info
    std::ostringstream user_query;
    user_query << "SELECT username, email FROM users WHERE id = " << user_id;
    
    if (mysql_query(conn, user_query.str().c_str()) == 0) {
        MYSQL_RES* result = mysql_store_result(conn);
        if (result) {
            MYSQL_ROW row = mysql_fetch_row(result);
            if (row) {
                stats.username = row[0] ? row[0] : "";
                stats.email = row[1] ? row[1] : "";
            }
            mysql_free_result(result);
        }
    }
    
    // Get violation stats
    std::ostringstream stats_query;
    stats_query << "SELECT "
                << "COUNT(*) as total, "
                << "SUM(CASE WHEN violation_type = 'disk_over' THEN 1 ELSE 0 END) as disk, "
                << "SUM(CASE WHEN violation_type = 'file_flood' THEN 1 ELSE 0 END) as flood, "
                << "SUM(CASE WHEN violation_type = 'illegal_file' THEN 1 ELSE 0 END) as illegal_file, "
                << "SUM(CASE WHEN violation_type = 'illegal_process' THEN 1 ELSE 0 END) as illegal_proc, "
                << "SUM(severity) as total_severity, "
                << "MAX(created_at) as last_violation "
                << "FROM user_violations WHERE user_id = " << user_id;
    
    if (mysql_query(conn, stats_query.str().c_str()) == 0) {
        MYSQL_RES* result = mysql_store_result(conn);
        if (result) {
            MYSQL_ROW row = mysql_fetch_row(result);
            if (row) {
                stats.total_violations = row[0] ? atoi(row[0]) : 0;
                stats.disk_violations = row[1] ? atoi(row[1]) : 0;
                stats.flood_violations = row[2] ? atoi(row[2]) : 0;
                stats.illegal_files = row[3] ? atoi(row[3]) : 0;
                stats.illegal_processes = row[4] ? atoi(row[4]) : 0;
                stats.total_severity = row[5] ? atoi(row[5]) : 0;
                stats.last_violation = row[6] ? atoi(row[6]) : 0;
            }
            mysql_free_result(result);
        }
    }
    
    // Check blacklist
    std::ostringstream blacklist_query;
    blacklist_query << "SELECT COUNT(*) FROM user_blacklist WHERE user_id = " << user_id;
    
    if (mysql_query(conn, blacklist_query.str().c_str()) == 0) {
        MYSQL_RES* result = mysql_store_result(conn);
        if (result) {
            MYSQL_ROW row = mysql_fetch_row(result);
            if (row) {
                stats.is_blacklisted = (atoi(row[0]) > 0);
            }
            mysql_free_result(result);
        }
    }
    
    return stats;
}

std::vector<UserStats> DatabaseTracker::get_top_offenders(int limit) {
    std::vector<UserStats> offenders;
    
    std::ostringstream query;
    query << "SELECT user_id, username, total_violations, disk_violations, "
          << "flood_violations, illegal_files, illegal_processes, "
          << "total_severity, last_violation "
          << "FROM top_offenders LIMIT " << limit;
    
    if (mysql_query(conn, query.str().c_str())) {
        logger.error("Failed to get top offenders");
        return offenders;
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) return offenders;
    
    MYSQL_ROW row;
    while ((row = mysql_fetch_row(result))) {
        UserStats stats;
        stats.user_id = row[0] ? atoi(row[0]) : 0;
        stats.username = row[1] ? row[1] : "";
        stats.total_violations = row[2] ? atoi(row[2]) : 0;
        stats.disk_violations = row[3] ? atoi(row[3]) : 0;
        stats.flood_violations = row[4] ? atoi(row[4]) : 0;
        stats.illegal_files = row[5] ? atoi(row[5]) : 0;
        stats.illegal_processes = row[6] ? atoi(row[6]) : 0;
        stats.total_severity = row[7] ? atoi(row[7]) : 0;
        stats.last_violation = row[8] ? atoi(row[8]) : 0;
        
        offenders.push_back(stats);
    }
    
    mysql_free_result(result);
    return offenders;
}

bool DatabaseTracker::blacklist_user(int user_id, const std::string& reason, 
                                      const std::string& blacklisted_by) {
    
    std::ostringstream query;
    query << "INSERT INTO user_blacklist (user_id, reason, blacklisted_by) "
          << "VALUES (" << user_id << ", '" << reason << "', '" << blacklisted_by << "') "
          << "ON DUPLICATE KEY UPDATE reason = '" << reason << "', "
          << "blacklisted_at = CURRENT_TIMESTAMP()";
    
    if (mysql_query(conn, query.str().c_str())) {
        logger.error("Failed to blacklist user: " + std::string(mysql_error(conn)));
        return false;
    }
    
    logger.warn("⛔ User " + std::to_string(user_id) + " blacklisted: " + reason);
    return true;
}

bool DatabaseTracker::unblacklist_user(int user_id) {
    std::ostringstream query;
    query << "DELETE FROM user_blacklist WHERE user_id = " << user_id;
    
    if (mysql_query(conn, query.str().c_str())) {
        logger.error("Failed to unblacklist user: " + std::string(mysql_error(conn)));
        return false;
    }
    
    logger.info("✅ User " + std::to_string(user_id) + " removed from blacklist");
    return true;
}

bool DatabaseTracker::is_blacklisted(int user_id) {
    std::ostringstream query;
    query << "SELECT COUNT(*) FROM user_blacklist WHERE user_id = " << user_id;
    
    if (mysql_query(conn, query.str().c_str())) return false;
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) return false;
    
    MYSQL_ROW row = mysql_fetch_row(result);
    bool blacklisted = (row && atoi(row[0]) > 0);
    
    mysql_free_result(result);
    return blacklisted;
}

std::vector<int> DatabaseTracker::get_blacklisted_users() {
    std::vector<int> users;
    
    if (mysql_query(conn, "SELECT user_id FROM user_blacklist")) {
        return users;
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) return users;
    
    MYSQL_ROW row;
    while ((row = mysql_fetch_row(result))) {
        users.push_back(atoi(row[0]));
    }
    
    mysql_free_result(result);
    return users;
}

void DatabaseTracker::update_daily_stats(int suspend, int files, int processes) {
    std::ostringstream query;
    query << "INSERT INTO daily_stats (date, total_suspend, total_files_deleted, total_process_killed) "
          << "VALUES (CURDATE(), " << suspend << ", " << files << ", " << processes << ") "
          << "ON DUPLICATE KEY UPDATE "
          << "total_suspend = total_suspend + " << suspend << ", "
          << "total_files_deleted = total_files_deleted + " << files << ", "
          << "total_process_killed = total_process_killed + " << processes;
    
    mysql_query(conn, query.str().c_str());
}

std::string DatabaseTracker::get_daily_report() {
    std::ostringstream report;
    
    MYSQL_RES* result;
    MYSQL_ROW row;
    
    // Today's stats
    if (mysql_query(conn, "SELECT total_suspend, total_files_deleted, total_process_killed, unique_users FROM daily_stats WHERE date = CURDATE()") == 0) {
        result = mysql_store_result(conn);
        if (result && (row = mysql_fetch_row(result))) {
            report << "📊 TODAY'S STATS\n";
            report << "├─ Suspend: " << (row[0] ? row[0] : "0") << "\n";
            report << "├─ Files Deleted: " << (row[1] ? row[1] : "0") << "\n";
            report << "├─ Processes Killed: " << (row[2] ? row[2] : "0") << "\n";
            report << "└─ Unique Users: " << (row[3] ? row[3] : "0") << "\n\n";
        }
        mysql_free_result(result);
    }
    
    // Top offenders
    auto offenders = get_top_offenders(5);
    if (!offenders.empty()) {
        report << "🔥 TOP OFFENDERS\n";
        for (size_t i = 0; i < offenders.size(); i++) {
            report << (i+1) << ". " << offenders[i].username 
                   << " (" << offenders[i].total_violations << " violations)\n";
        }
    }
    
    return report.str();
}

bool DatabaseTracker::track_illegal_file(const std::string& file_hash, 
                                          const std::string& file_name,
                                          const std::string& file_path,
                                          const std::string& server_uuid,
                                          int user_id,
                                          const std::string& reason,
                                          long long file_size) {
    
    std::ostringstream query;
    query << "INSERT INTO illegal_files (file_hash, file_name, file_path, "
          << "server_uuid, user_id, detection_reason, file_size) "
          << "VALUES ('" << file_hash << "', '" << file_name << "', '"
          << file_path << "', '" << server_uuid << "', " << user_id
          << ", '" << reason << "', " << file_size << ") "
          << "ON DUPLICATE KEY UPDATE "
          << "last_seen = CURRENT_TIMESTAMP(), "
          << "seen_count = seen_count + 1";
    
    if (mysql_query(conn, query.str().c_str())) {
        logger.error("Failed to track illegal file: " + std::string(mysql_error(conn)));
        return false;
    }
    
    return true;
}

void DatabaseTracker::cleanup_old_records(int days) {
    std::ostringstream query;
    query << "DELETE FROM user_violations WHERE created_at < NOW() - INTERVAL " << days << " DAY";
    mysql_query(conn, query.str().c_str());
    
    logger.info("🧹 Cleaned up old violation records");
}

std::string DatabaseTracker::generate_offender_report() {
    std::ostringstream report;
    report << "📋 OFFENDER REPORT\n";
    report << "━━━━━━━━━━━━━━\n\n";
    
    auto offenders = get_top_offenders(10);
    
    for (size_t i = 0; i < offenders.size(); i++) {
        report << (i+1) << ". " << offenders[i].username << "\n";
        report << "   ├─ Total: " << offenders[i].total_violations << "\n";
        report << "   ├─ Disk: " << offenders[i].disk_violations << "\n";
        report << "   ├─ Flood: " << offenders[i].flood_violations << "\n";
        report << "   ├─ Illegal Files: " << offenders[i].illegal_files << "\n";
        report << "   └─ Illegal Proc: " << offenders[i].illegal_processes << "\n\n";
    }
    
    return report.str();
}

DatabaseTracker tracker_db;