#include "logger.h"
#include <iostream>
#include <sstream>
#include <chrono>
#include <iomanip>

Logger::Logger() : min_level(LOG_INFO) {}

Logger::~Logger() {
    if (log_file.is_open()) {
        log_file.close();
    }
}

void Logger::init(const std::string& path, LogLevel level) {
    log_path = path;
    min_level = level;
    
    log_file.open(path, std::ios::app);
    if (!log_file.is_open()) {
        std::cerr << "Failed to open log file: " << path << std::endl;
    }
}

std::string Logger::get_timestamp() {
    auto now = std::chrono::system_clock::now();
    auto in_time_t = std::chrono::system_clock::to_time_t(now);
    
    std::tm bt;
    localtime_r(&in_time_t, &bt);
    // Use system timezone
    
    std::ostringstream oss;
    oss << std::put_time(&bt, "%H:%M:%S");
    return oss.str();
}

std::string Logger::level_to_string(LogLevel level) {
    switch(level) {
        case LOG_INFO: return "INFO";
        case LOG_WARN: return "WARN";
        case LOG_ERROR: return "ERROR";
        case LOG_DEBUG: return "DEBUG";
        default: return "UNKNOWN";
    }
}

void Logger::log(LogLevel level, const std::string& message) {
    if (level < min_level) return;
    
    std::string timestamp = get_timestamp();
    std::string level_str = level_to_string(level);
    
    // Console
    std::cout << "[" << timestamp << "] [" << level_str << "] " << message << std::endl;
    
    // File
    if (log_file.is_open()) {
        log_file << "[" << timestamp << "] [" << level_str << "] " << message << std::endl;
        log_file.flush();
    }
}

void Logger::info(const std::string& message) {
    log(LOG_INFO, message);
}

void Logger::warn(const std::string& message) {
    log(LOG_WARN, message);
}

void Logger::error(const std::string& message) {
    log(LOG_ERROR, message);
}

void Logger::debug(const std::string& message) {
    log(LOG_DEBUG, message);
}

Logger logger;