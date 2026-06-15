#include "db_guard.h"
#include "logger.h"
#include <mysql/mysql.h>
#include <sstream>

DatabaseGuard::DatabaseGuard() : conn(nullptr) {}

DatabaseGuard::~DatabaseGuard() {
    if (conn) {
        mysql_close(conn);
    }
}

bool DatabaseGuard::init(const std::string& h, const std::string& u, 
                          const std::string& p, const std::string& db) {
    host = h;
    user = u;
    password = p;
    dbname = db;
    
    return connect();
}

bool DatabaseGuard::connect() {
    conn = mysql_init(nullptr);
    if (!conn) {
        logger.error("MySQL init failed");
        return false;
    }
    
    if (!mysql_real_connect(conn, host.c_str(), user.c_str(), password.c_str(), 
                            dbname.c_str(), 3306, nullptr, 0)) {
        logger.error("MySQL connect failed: " + std::string(mysql_error(conn)));
        return false;
    }
    
    logger.info("MySQL Connected");
    return true;
}

ServerInfo DatabaseGuard::get_server_info(const std::string& uuid) {
    ServerInfo info;
    info.id = -1;
    info.uuid = uuid;
    
    std::ostringstream query;
    query << "SELECT id, name, owner_id FROM servers WHERE uuid = '" << uuid << "'";
    
    if (mysql_query(conn, query.str().c_str())) {
        return info;
    }
    
    MYSQL_RES* result = mysql_store_result(conn);
    if (!result) return info;
    
    MYSQL_ROW row = mysql_fetch_row(result);
    if (row) {
        info.id = row[0] ? atoi(row[0]) : -1;
        info.name = row[1] ? row[1] : "";
        int owner_id = row[2] ? atoi(row[2]) : -1;
        
        mysql_free_result(result);
        
        if (owner_id > 0) {
            std::ostringstream user_query;
            user_query << "SELECT username, email, name_first, name_last FROM users WHERE id = " << owner_id;
            
            if (mysql_query(conn, user_query.str().c_str()) == 0) {
                MYSQL_RES* user_result = mysql_store_result(conn);
                if (user_result) {
                    MYSQL_ROW user_row = mysql_fetch_row(user_result);
                    if (user_row) {
                        info.username = user_row[0] ? user_row[0] : "";
                        info.email = user_row[1] ? user_row[1] : "";
                        info.first_name = user_row[2] ? user_row[2] : "";
                        info.last_name = user_row[3] ? user_row[3] : "";
                    }
                    mysql_free_result(user_result);
                }
            }
        }
    } else {
        mysql_free_result(result);
    }
    
    return info;
}

bool DatabaseGuard::suspend_server(int server_id) {
    std::ostringstream query;
    query << "UPDATE servers SET status = 'suspended' WHERE id = " << server_id;
    
    return mysql_query(conn, query.str().c_str()) == 0;
}

DatabaseGuard db;