#include "telegram.h"
#include "logger.h"
#include "db_guard.h"
#include <curl/curl.h>
#include <sstream>
#include <ctime>

TelegramBot::TelegramBot() {}

void TelegramBot::init(const std::string& t, const std::string& cid,
                       const std::string& ch, const std::string& rep, 
                       const std::string& cr) {
    token = t;
    chat_id = cid;
    channel = ch;
    report_channel = rep;
    creator = cr;
}

size_t TelegramBot::write_callback(void* contents, size_t size, size_t nmemb, std::string* output) {
    size_t total = size * nmemb;
    output->append((char*)contents, total);
    return total;
}

bool TelegramBot::send_message(const std::string& message) {
    CURL* curl = curl_easy_init();
    if (!curl) return false;
    
    char* escaped = curl_easy_escape(curl, message.c_str(), message.length());
    if (!escaped) {
        curl_easy_cleanup(curl);
        return false;
    }
    
    std::string url = "https://api.telegram.org/bot" + token + "/sendMessage";
    std::string data = "chat_id=" + chat_id + "&text=" + escaped;
    curl_free(escaped);
    
    std::string response;
    
    curl_easy_setopt(curl, CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, data.c_str());
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 5L);
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_callback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    
    CURLcode res = curl_easy_perform(curl);
    curl_easy_cleanup(curl);
    
    return res == CURLE_OK;
}

void TelegramBot::notify_suspend(const ServerInfo& info, const std::string& reason, 
                                  const std::string& details, const std::string& action) {
    std::string owner = info.first_name + " " + info.last_name;
    if (owner == " " || owner.empty()) owner = info.username;
    if (owner.empty()) owner = "Unknown";
    
    std::ostringstream msg;
    msg << "🛡️ DANN GUARD\n"
        << "🚨 SERVER SUSPENDED\n"
        << "⏱️ " << std::time(nullptr) << "\n"
        << "━━━━━━━━━━━━━━━━━━━\n\n"
        << "👤 OWNER\n"
        << "├─ Nama: " << owner << "\n"
        << "├─ Username: @" << info.username << "\n"
        << "├─ Email: " << info.email << "\n"
        << "└─ ID: " << info.id << "\n\n"
        << "📦 SERVER\n"
        << "├─ Nama: " << info.name << "\n"
        << "├─ UUID: " << info.uuid.substr(0, 8) << "...\n"
        << "└─ ID: " << info.id << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "📊 DETAIL\n" << details << "\n\n"
        << "🛑 ALASAN\n" << reason << "\n\n"
        << "✅ ACTION\n" << action << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "👤 Creator: " << creator << "\n"
        << "📢 Channel: " << channel << "\n"
        << "📢 Report: " << report_channel;
    
    send_message(msg.str());
}

void TelegramBot::notify_files_deleted(const ServerInfo& info, const std::string& files) {
    std::string owner = info.first_name + " " + info.last_name;
    if (owner == " " || owner.empty()) owner = info.username;
    
    std::ostringstream msg;
    msg << "🛡️ DANN GUARD\n"
        << "🚨 ILLEGAL FILES DETECTED\n"
        << "⏱️ " << std::time(nullptr) << "\n"
        << "━━━━━━━━━━━━━━━━━━━\n\n"
        << "👤 OWNER\n"
        << "├─ Nama: " << owner << "\n"
        << "├─ Username: @" << info.username << "\n"
        << "├─ Email: " << info.email << "\n"
        << "└─ ID: " << info.id << "\n\n"
        << "📦 SERVER\n"
        << "├─ Nama: " << info.name << "\n"
        << "├─ UUID: " << info.uuid.substr(0, 8) << "...\n"
        << "└─ ID: " << info.id << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "📊 FILES DELETED\n" << files << "\n"
        << "🛑 ALASAN\n"
        << "File ilegal/berbahaya terdeteksi\n\n"
        << "✅ ACTION\n"
        << "Files dihapus, server tetap aktif\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "👤 Creator: " << creator << "\n"
        << "📢 Channel: " << channel << "\n"
        << "📢 Report: " << report_channel;
    
    send_message(msg.str());
}

void TelegramBot::notify_process_killed(const ServerInfo& info, int pid, 
                                         const std::string& pname, const std::string& reason) {
    std::string owner = info.first_name + " " + info.last_name;
    if (owner == " " || owner.empty()) owner = info.username;
    
    std::ostringstream msg;
    msg << "🛡️ DANN GUARD\n"
        << "🚨 ILLEGAL PROCESS KILLED\n"
        << "⏱️ " << std::time(nullptr) << "\n"
        << "━━━━━━━━━━━━━━━━━━━\n\n"
        << "👤 OWNER\n"
        << "├─ Nama: " << owner << "\n"
        << "├─ Username: @" << info.username << "\n"
        << "├─ Email: " << info.email << "\n"
        << "└─ ID: " << info.id << "\n\n"
        << "📦 SERVER\n"
        << "├─ Nama: " << info.name << "\n"
        << "├─ UUID: " << info.uuid.substr(0, 8) << "...\n"
        << "└─ ID: " << info.id << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "📊 PROCESS DETAILS\n"
        << "├─ PID: " << pid << "\n"
        << "├─ Name: " << pname << "\n"
        << "└─ Reason: " << reason << "\n\n"
        << "🛑 ALASAN\n"
        << "Proses ilegal terdeteksi\n\n"
        << "✅ ACTION\n"
        << "Process killed\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "👤 Creator: " << creator << "\n"
        << "📢 Channel: " << channel << "\n"
        << "📢 Report: " << report_channel;
    
    send_message(msg.str());
}

void TelegramBot::notify_flood(const ServerInfo& info, int new_files, const std::string& pattern) {
    std::string owner = info.first_name + " " + info.last_name;
    if (owner == " " || owner.empty()) owner = info.username;
    
    std::ostringstream msg;
    msg << "🛡️ DANN GUARD\n"
        << "🚨 FILE FLOOD DETECTED\n"
        << "⏱️ " << std::time(nullptr) << "\n"
        << "━━━━━━━━━━━━━━━━━━━\n\n"
        << "👤 OWNER\n"
        << "├─ Nama: " << owner << "\n"
        << "├─ Username: @" << info.username << "\n"
        << "├─ Email: " << info.email << "\n"
        << "└─ ID: " << info.id << "\n\n"
        << "📦 SERVER\n"
        << "├─ Nama: " << info.name << "\n"
        << "├─ UUID: " << info.uuid.substr(0, 8) << "...\n"
        << "└─ ID: " << info.id << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "📊 DETAIL\n"
        << "├─ New Files: " << new_files << "\n"
        << "├─ Pattern: " << pattern << "\n"
        << "🛑 ALASAN\n"
        << "File flood terdeteksi\n\n"
        << "✅ ACTION\n"
        << "Server suspended + cleaned\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "👤 Creator: " << creator << "\n"
        << "📢 Channel: " << channel << "\n"
        << "📢 Report: " << report_channel;
    
    send_message(msg.str());
}

void TelegramBot::notify_disk_over(const ServerInfo& info, double total_gb, int file_count) {
    std::string owner = info.first_name + " " + info.last_name;
    if (owner == " " || owner.empty()) owner = info.username;
    
    std::ostringstream msg;
    msg << "🛡️ DANN GUARD\n"
        << "🚨 DISK OVER LIMIT\n"
        << "⏱️ " << std::time(nullptr) << "\n"
        << "━━━━━━━━━━━━━━━━━━━\n\n"
        << "👤 OWNER\n"
        << "├─ Nama: " << owner << "\n"
        << "├─ Username: @" << info.username << "\n"
        << "├─ Email: " << info.email << "\n"
        << "└─ ID: " << info.id << "\n\n"
        << "📦 SERVER\n"
        << "├─ Nama: " << info.name << "\n"
        << "├─ UUID: " << info.uuid.substr(0, 8) << "...\n"
        << "└─ ID: " << info.id << "\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "📊 DETAIL\n"
        << "├─ Size: " << total_gb << "GB\n"
        << "└─ Files: " << file_count << "\n\n"
        << "🛑 ALASAN\n"
        << "Disk melebihi batas\n\n"
        << "✅ ACTION\n"
        << "Server suspended + cleaned\n\n"
        << "━━━━━━━━━━━━━━━━━━━\n"
        << "👤 Creator: " << creator << "\n"
        << "📢 Channel: " << channel << "\n"
        << "📢 Report: " << report_channel;
    
    send_message(msg.str());
}

TelegramBot bot;