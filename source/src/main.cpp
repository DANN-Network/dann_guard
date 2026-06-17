#include <iostream>
#include <thread>
#include <chrono>
#include <signal.h>
#include <iomanip>
#include <cstring>
#include <dirent.h>

#include "config.h"
#include "logger.h"
#include "db_guard.h"
#include "telegram.h"
#include "disk_protect.h"
#include "tracker_db.h"
#include "rate_protect.h"

Config config;
bool running = true;
time_t last_report_time = 0;
time_t last_offender_report = 0;

void signal_handler(int sig) {
    logger.info("Received signal " + std::to_string(sig) + ", shutting down...");
    running = false;
}

void send_periodic_reports() {
    time_t now = time(nullptr);
    
    // Daily stats report (every 6 hours)
    if (now - last_report_time > 21600) { // 6 hours
        std::string report = tracker_db.get_daily_report();
        if (!report.empty()) {
            bot.send_message("📊 PERIODIC REPORT\n" + report);
        }
        last_report_time = now;
    }
    
    // Top offenders report (every 24 hours)
    if (now - last_offender_report > 86400) { // 24 hours
        std::string offenders = tracker_db.generate_offender_report();
        if (!offenders.empty()) {
            bot.send_message(offenders);
        }
        last_offender_report = now;
    }
}

void check_blacklisted_users() {
    static time_t last_blacklist_check = 0;
    time_t now = time(nullptr);
    
    // Check every hour
    if (now - last_blacklist_check > 3600) {
        auto blacklisted = tracker_db.get_blacklisted_users();
        if (!blacklisted.empty()) {
            logger.info("🚫 Blacklisted users: " + std::to_string(blacklisted.size()));
            
            // Optional: auto-suspend all servers of blacklisted users
            // This would require additional database queries
        }
        last_blacklist_check = now;
    }
}

int main() {
    // Setup signal handlers
    signal(SIGINT, signal_handler);
    signal(SIGTERM, signal_handler);
    
    // Load config
    config = Config::load("/root/dann_guard/config.json");
    
    // Initialize logger
    logger.init("/var/log/dann_guard.log");
    logger.info("🚀 DANN GUARD PROFESSIONAL STARTING...");
    logger.info("⚡ Version: Ultimate with User Tracking");
    
    // Initialize database
    if (!db.init(config.database.host, config.database.user,
                 config.database.password, config.database.name)) {
        logger.error("❌ Failed to initialize database");
        return 1;
    }
    
    // Initialize tracker database (same connection or separate? Using same for simplicity)
    if (!tracker_db.init(config.database.host, config.database.user,
                          config.database.password, config.database.name)) {
        logger.warn("⚠️ Tracker database not initialized, tracking disabled");
        // Non-critical, continue anyway
    }
    
    // Initialize telegram
    bot.init(config.telegram.token, config.telegram.chat_id,
             config.telegram.channel, config.telegram.report_channel,
             config.telegram.creator);
    
    // Initialize disk protector
    disk.init(config.paths.volumes, config.limits.max_disk_gb,
              config.limits.max_file_size_mb, config.limits.max_file_flood,
              config.limits.flood_window);
    
    // Send startup notification
    std::ostringstream start_msg;
    start_msg << "🛡️ DANN GUARD ULTIMATE\n"
              << "🚨 SYSTEM STARTED\n"
              << "⏱️ " << std::time(nullptr) << "\n"
              << "━━━━━━━━━━━━━━━━━━━\n\n"
              << "📌 Monitoring:\n"
              << "├─ Disk > " << config.limits.max_disk_gb << "GB → SUSPEND\n"
              << "├─ File Flood → SUSPEND\n"
              << "├─ Illegal Files → DELETE + TRACK\n"
              << "├─ User Tracking → ENABLED\n"
              << "└─ Blacklist → ACTIVE\n\n"
              << "━━━━━━━━━━━━━━━━━━━\n"
              << "👤 Creator: @gantengdann\n"
              << "📢 Channel: @aboutdannz\n"
              << "📢 Report: @reportdann";
    
    bot.send_message(start_msg.str());
    logger.info("✅ Guard started - Interval: " + std::to_string(config.limits.check_interval) + "s");
    
    // Initial reports
    last_report_time = time(nullptr);
    last_offender_report = time(nullptr);
    
    // Main loop
    int loop = 0;
    while (running) {
        try {
            loop++;
            logger.info("========== LOOP #" + std::to_string(loop) + " ==========");
            
            // Scan all servers
            disk.scan_all();
            
            // Scan ZIP files for all server directories
            DIR* vdir = opendir(config.paths.volumes.c_str());
            if (vdir) {
                struct dirent* ventry;
                while ((ventry = readdir(vdir)) != nullptr) {
                    if (strlen(ventry->d_name) == 36) {
                        disk.scan_zip_files(ventry->d_name);
                    }
                }
                closedir(vdir);
            }
            
            // Scan for DDoS processes
            disk.scan_processes();
            
            // Anti local DDoS check (every 5 loops to reduce overhead)
            if (loop % 5 == 0) {
                DIR* ddir = opendir(config.paths.volumes.c_str());
                if (ddir) {
                    struct dirent* dentry;
                    while ((dentry = readdir(ddir)) != nullptr) {
                        if (strlen(dentry->d_name) == 36) {
                            disk.check_server_ddos(dentry->d_name);
                        }
                    }
                    closedir(ddir);
                }
            }
            
            // Check blacklisted users
            check_blacklisted_users();
            
            // Send periodic reports
            send_periodic_reports();
            
            // Log stats
            if (loop % 10 == 0) {
                logger.info("📈 System running - Loop count: " + std::to_string(loop));
            }
            
            std::this_thread::sleep_for(std::chrono::seconds(config.limits.check_interval));
            
        } catch (const std::exception& e) {
            logger.error("❌ Exception in main loop: " + std::string(e.what()));
            std::this_thread::sleep_for(std::chrono::seconds(5));
        } catch (...) {
            logger.error("❌ Unknown exception in main loop");
            std::this_thread::sleep_for(std::chrono::seconds(5));
        }
    }
    
    // Shutdown
    logger.info("🛑 DANN GUARD SHUTTING DOWN...");
    bot.send_message("🛡️ DANN GUARD\n🚨 SYSTEM STOPPED\n⏱️ " + std::to_string(time(nullptr)));
    
    logger.info("✅ Guard stopped gracefully");
    return 0;
}