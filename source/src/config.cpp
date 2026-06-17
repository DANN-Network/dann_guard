#include "config.h"
#include <fstream>
#include <iostream>
#include <nlohmann/json.hpp>

using json = nlohmann::json;

Config Config::load(const std::string& filename) {
    Config cfg;
    std::ifstream file(filename);
    
    if (!file.is_open()) {
        std::cerr << "Failed to open config file: " << filename << std::endl;
        return cfg;
    }
    
    json j;
    file >> j;
    
    // Database
    cfg.database.host = j["database"]["host"];
    cfg.database.user = j["database"]["user"];
    cfg.database.password = j["database"]["password"];
    cfg.database.name = j["database"]["name"];
    
    // Telegram
    cfg.telegram.token = j["telegram"]["token"];
    cfg.telegram.chat_id = j["telegram"]["chat_id"];
    cfg.telegram.channel = j["telegram"]["channel"];
    cfg.telegram.report_channel = j["telegram"]["report_channel"];
    cfg.telegram.creator = j["telegram"]["creator"];
    
    // Paths
    cfg.paths.volumes = j["paths"]["volumes"];
    
    // Limits
    cfg.limits.check_interval = j["limits"].value("check_interval", 60);
    cfg.limits.max_disk_gb = j["limits"].value("max_disk_gb", 20.0);
    cfg.limits.max_file_size_mb = j["limits"].value("max_file_size_mb", 100);
    cfg.limits.max_file_flood = j["limits"].value("max_file_flood", 50);
    cfg.limits.flood_window = j["limits"].value("flood_window", 300);
    
    // Process scan config
    cfg.process_scan.enabled = j["process_scan"].value("enabled", true);
    cfg.process_scan.keywords = j["process_scan"]["keywords"].get<std::vector<std::string>>();
    cfg.process_scan.max_cpu = j["process_scan"].value("max_cpu", 90);
    cfg.process_scan.max_outbound_conns = j["process_scan"].value("max_outbound_conns", 50);
    
    // Anti-DDoS config
    cfg.anti_ddos.enabled = j["anti_ddos"].value("enabled", true);
    cfg.anti_ddos.max_outbound = j["anti_ddos"].value("max_outbound", 100);
    cfg.anti_ddos.check_interval = j["anti_ddos"].value("check_interval", 60);
    
    return cfg;
}

void Config::save(const std::string& filename) {
    json j;
    
    j["database"]["host"] = database.host;
    j["database"]["user"] = database.user;
    j["database"]["password"] = database.password;
    j["database"]["name"] = database.name;
    
    j["telegram"]["token"] = telegram.token;
    j["telegram"]["chat_id"] = telegram.chat_id;
    j["telegram"]["channel"] = telegram.channel;
    j["telegram"]["report_channel"] = telegram.report_channel;
    j["telegram"]["creator"] = telegram.creator;
    
    j["paths"]["volumes"] = paths.volumes;
    
    j["limits"]["check_interval"] = limits.check_interval;
    j["limits"]["max_disk_gb"] = limits.max_disk_gb;
    j["limits"]["max_file_size_mb"] = limits.max_file_size_mb;
    j["limits"]["max_file_flood"] = limits.max_file_flood;
    j["limits"]["flood_window"] = limits.flood_window;
    
    j["process_scan"]["enabled"] = process_scan.enabled;
    j["process_scan"]["keywords"] = process_scan.keywords;
    j["process_scan"]["max_cpu"] = process_scan.max_cpu;
    j["process_scan"]["max_outbound_conns"] = process_scan.max_outbound_conns;
    
    j["anti_ddos"]["enabled"] = anti_ddos.enabled;
    j["anti_ddos"]["max_outbound"] = anti_ddos.max_outbound;
    j["anti_ddos"]["check_interval"] = anti_ddos.check_interval;
    
    std::ofstream file(filename);
    file << j.dump(4);
}