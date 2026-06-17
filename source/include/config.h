#ifndef CONFIG_H
#define CONFIG_H

#include <string>
#include <nlohmann/json.hpp>

using json = nlohmann::json;

struct DatabaseConfig {
    std::string host;
    std::string user;
    std::string password;
    std::string name;
};

struct TelegramConfig {
    std::string token;
    std::string chat_id;
    std::string channel;
    std::string report_channel;
    std::string creator;
};

struct PathsConfig {
    std::string volumes;
};

struct LimitsConfig {
    int check_interval;
    double max_disk_gb;
    int max_file_size_mb;
    int max_file_flood;
    int flood_window;
};

struct ProcessScanConfig {
    bool enabled;
    std::vector<std::string> keywords;
    int max_cpu;
    int max_outbound_conns;
};

struct DDoSConfig {
    bool enabled;
    int max_outbound;
    int check_interval;
};

struct Config {
    DatabaseConfig database;
    TelegramConfig telegram;
    PathsConfig paths;
    LimitsConfig limits;
    ProcessScanConfig process_scan;
    DDoSConfig anti_ddos;
    
    static Config load(const std::string& filename);
    void save(const std::string& filename);
};

#endif