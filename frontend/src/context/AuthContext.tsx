import { useQuery, useQueryClient } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import { useCallback, useEffect } from "react";
import type { ReactNode } from "react";
import { api, resetCsrf } from "../api/client";
import type { ApiEnvelope, User } from "../types/api";
import { AuthContext } from "./auth-context";

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const { data: user = null, isLoading } = useQuery({
        queryKey: ["auth", "me"],
        queryFn: async () => {
            try {
                return (await api.get<ApiEnvelope<User>>("/auth/me")).data.data;
            } catch (error) {
                // Chỉ 401 nghĩa là chưa đăng nhập; lỗi mạng/5xx thì giữ nguyên người dùng hiện tại.
                if (isAxiosError(error) && error.response?.status === 401) {
                    return null;
                }
                throw error;
            }
        },
        retry: false,
    });

    const clearAuthState = useCallback(() => {
        // Cập nhật ngay lập tức query auth thành null để mọi subscriber re-render ngay
        queryClient.setQueryData(["auth", "me"], null);
        // Xóa toàn bộ cache dữ liệu cũ (đơn đặt, doanh thu, thông tin cá nhân...)
        queryClient.clear();
        // Đảm bảo sau khi clear cache, query auth vẫn giữ giá trị null
        queryClient.setQueryData(["auth", "me"], null);

        try {
            localStorage.removeItem("sporthub:last-mode");
        } catch {
            // bỏ qua nếu storage bị giới hạn
        }

        resetCsrf();
    }, [queryClient]);

    // Phiên hết hạn giữa chừng (401/419 ở request bất kỳ): xoá người dùng để route guard đưa về /login.
    useEffect(() => {
        const id = api.interceptors.response.use(undefined, (error: unknown) => {
            const status = isAxiosError(error) ? error.response?.status : undefined;
            const url = isAxiosError(error) ? (error.config?.url ?? "") : "";
            if ((status === 401 || status === 419) && !url.startsWith("/auth/")) {
                clearAuthState();
            }
            return Promise.reject(error);
        });
        return () => api.interceptors.response.eject(id);
    }, [clearAuthState]);

    const authenticate = async (
        path: string,
        payload: Record<string, unknown>,
    ) => {
        const nextUser = (await api.post<ApiEnvelope<User>>(path, payload)).data
            .data;
        queryClient.setQueryData(["auth", "me"], nextUser);
        return nextUser;
    };

    const logout = async () => {
        try {
            // Yêu cầu backend hủy session.
            await api.post("/auth/logout");
        } catch (error) {
            // Không để lỗi từ backend (ví dụ session đã hết hạn 401/419) chặn quá trình dọn dẹp và điều hướng ở frontend
            console.warn("Lỗi khi gọi API đăng xuất:", error);
        } finally {
            clearAuthState();
        }
    };

    return (
        <AuthContext.Provider
            value={{
                user,
                isLoading,
                login: (payload) => authenticate("/auth/login", payload),
                register: (payload) => authenticate("/auth/register", payload),
                logout,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}
