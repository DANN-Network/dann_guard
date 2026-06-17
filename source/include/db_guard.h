#ifndef DB_GUARD_H
#define DB_GUARD_H

#include <string>
#include <mysql/mysql.h>

struct ServerInfo {
    int id;
    std::string uuid;
    std::string name;
    std::string username;
    std::string email;
    std::string first_name;
    std::string last_name;
};

class DatabaseGuard {
private:
    MYSQL* conn;
    std::string host;
    std::string user;
    std::string password;
    std::string dbname;
    
    bool connect();
    
public:
    DatabaseGuard();
    ~DatabaseGuard();
    
    bool init(const std::string& h, const std::string& u, 
              const std::string& p, const std::string& db);
    
    ServerInfo get_server_info(const std::string& uuid);
    bool suspend_server(int server_id);
};

extern DatabaseGuard db;

#endif