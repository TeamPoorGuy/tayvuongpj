import { useQuery, useQueryClient } from "@tanstack/react-query";
import type { ReactNode } from "react";
import { api, initializeCsrf } from "../api/client";
import type { ApiEnvelope, User } from "../types/api";
import { AuthContext } from "./auth-context";

export function AuthProvider({ children }: { children: ReactNode }) {
    const queryClient = useQueryClient();
    const { data: user = null, isLoading } = useQuery({
        queryKey: ["auth", "me"],
        queryFn: async () => {
            try {
                return (await api.get<ApiEnvelope<User>>("/auth/me")).data.data;
            } catch {
                return null;
            }
        },
        retry: false,
    });

    const authenticate = async (
        path: string,
        payload: Record<string, unknown>,
    ) => {
        await initializeCsrf();
        const nextUser = (await api.post<ApiEnvelope<User>>(path, payload)).data
            .data;
        queryClient.setQueryData(["auth", "me"], nextUser);
        return nextUser;
    };

    const logout = async () => {
        try {
            // Lấy CSRF cookie trước khi gửi request POST.
            await initializeCsrf();

            // Yêu cầu backend hủy session.
            await api.post("/auth/logout");
        } finally {
            /*
             * finally luôn chạy, kể cả khi API logout bị lỗi.
             *
             * Nhờ vậy frontend vẫn xóa thông tin người dùng
             * và không bị kẹt ở trạng thái đang đăng nhập.
             */
            queryClient.clear();
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
